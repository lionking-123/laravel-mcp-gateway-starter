<?php

namespace Tests\Unit;

use App\Mcp\SchemaValidator;
use PHPUnit\Framework\TestCase;

class SchemaValidatorTest extends TestCase
{
    private SchemaValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new SchemaValidator;
    }

    public function test_valid_object_passes(): void
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 50],
                'from' => ['type' => 'string', 'format' => 'date'],
                'tags' => ['type' => 'array', 'items' => ['type' => 'string'], 'maxItems' => 2],
            ],
            'required' => ['from'],
            'additionalProperties' => false,
        ];

        $this->assertSame([], $this->validator->validate($schema, ['limit' => 10, 'from' => '2026-02-28', 'tags' => ['a', 'b']]));
    }

    public function test_reports_every_problem_with_a_path(): void
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 50],
                'from' => ['type' => 'string', 'format' => 'date'],
                'status' => ['type' => 'string', 'enum' => ['a', 'b']],
            ],
            'required' => ['from'],
            'additionalProperties' => false,
        ];

        $errors = $this->validator->validate($schema, ['limit' => 0, 'status' => 'c', 'extra' => true]);

        $this->assertSame([
            'arguments.from is required',
            'arguments.limit must be at least 1',
            'arguments.status must be one of: a, b',
            'arguments.extra is not an accepted argument',
        ], $errors);
    }

    public function test_type_mismatch_short_circuits(): void
    {
        $this->assertSame(['arguments.n must be of type integer'], $this->validator->validate(
            ['type' => 'object', 'properties' => ['n' => ['type' => 'integer', 'minimum' => 5]]],
            ['n' => '7'],
        ));
    }

    public function test_date_format_checks_the_calendar(): void
    {
        $schema = ['type' => 'string', 'format' => 'date'];

        $this->assertSame([], $this->validator->validate($schema, '2024-02-29'));
        $this->assertNotEmpty($this->validator->validate($schema, '2023-02-29'));
        $this->assertNotEmpty($this->validator->validate($schema, '2026-1-5'));
    }

    public function test_boolean_enum_pins_a_constant(): void
    {
        $schema = ['type' => 'boolean', 'enum' => [true]];

        $this->assertSame([], $this->validator->validate($schema, true));
        $this->assertSame(['arguments must be one of: true'], $this->validator->validate($schema, false));
    }

    public function test_empty_object_and_list_are_told_apart(): void
    {
        $this->assertSame([], $this->validator->validate(['type' => 'object'], []));
        $this->assertNotEmpty($this->validator->validate(['type' => 'object'], [1, 2]));
        $this->assertNotEmpty($this->validator->validate(['type' => 'array'], ['a' => 1]));
    }
}
