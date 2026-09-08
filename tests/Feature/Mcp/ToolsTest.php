<?php

namespace Tests\Feature\Mcp;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\McpAuditLog;
use App\Models\ServiceJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ToolsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
    }

    public function test_list_jobs_returns_only_allow_listed_fields(): void
    {
        $customer = Customer::factory()->create(['email' => 'secret@example.com', 'phone' => '555-0100', 'internal_notes' => 'Do not expose']);
        ServiceJob::factory()->count(3)->for($customer)->create(['internal_notes' => 'private']);

        $response = $this->callTool('list_jobs', [], $this->user)->assertOk();

        $response->assertJsonPath('result.isError', false)
            ->assertJsonPath('result.structuredContent.count', 3)
            ->assertJsonPath('result.structuredContent.next_cursor', null);

        $json = json_encode($response->json('result'));
        $this->assertStringNotContainsString('secret@example.com', $json);
        $this->assertStringNotContainsString('555-0100', $json);
        $this->assertStringNotContainsString('Do not expose', $json);
        $this->assertStringNotContainsString('internal_notes', $json);

        $first = $response->json('result.structuredContent.items.0');
        $this->assertSame($customer->name, $first['customer']['name']);
        $this->assertArrayHasKey('amount_display', $first);
    }

    public function test_list_jobs_filters_and_paginates(): void
    {
        ServiceJob::factory()->count(4)->create(['status' => 'scheduled']);
        ServiceJob::factory()->count(2)->create(['status' => 'completed']);

        $page1 = $this->callTool('list_jobs', ['status' => 'scheduled', 'limit' => 3], $this->user)
            ->assertJsonPath('result.structuredContent.count', 3);

        $cursor = $page1->json('result.structuredContent.next_cursor');
        $this->assertNotNull($cursor);

        $page2 = $this->callTool('list_jobs', ['status' => 'scheduled', 'limit' => 3, 'cursor' => $cursor], $this->user)
            ->assertJsonPath('result.structuredContent.count', 1)
            ->assertJsonPath('result.structuredContent.next_cursor', null);

        $ids = [...$page1->json('result.structuredContent.items.*.id'), ...$page2->json('result.structuredContent.items.*.id')];
        $this->assertCount(4, array_unique($ids));
    }

    public function test_list_jobs_rejects_bad_cursor_as_tool_error(): void
    {
        $this->callTool('list_jobs', ['cursor' => 'nonsense'], $this->user)
            ->assertOk()
            ->assertJsonPath('result.isError', true)
            ->assertJsonPath('result.content.0.type', 'text');
    }

    public function test_invalid_arguments_are_a_json_rpc_error_with_details(): void
    {
        $response = $this->callTool('list_jobs', ['status' => 'bogus', 'limit' => 999, 'unknown' => 1], $this->user);

        // Method-level failures are JSON-RPC errors inside an HTTP 200, per the spec.
        // Only malformed bodies (parse errors, batches) get an HTTP 400.
        $response->assertOk()->assertJsonPath('error.code', -32602)->assertJsonMissingPath('result');

        $errors = $response->json('error.data.errors');
        $this->assertCount(3, $errors);
        $this->assertStringContainsString('status must be one of', implode(' ', $errors));
        $this->assertStringContainsString('unknown is not an accepted argument', implode(' ', $errors));
    }

    public function test_unknown_tool_is_a_json_rpc_error_and_is_audited(): void
    {
        $this->callTool('drop_tables', [], $this->user)
            ->assertOk()
            ->assertJsonPath('error.code', -32602)
            ->assertJsonPath('error.message', 'Unknown tool: drop_tables');

        $this->assertDatabaseHas('mcp_audit_logs', ['tool' => 'drop_tables', 'status' => 'unknown_tool']);
    }

    public function test_get_job_returns_detail_with_invoices(): void
    {
        $job = ServiceJob::factory()->create(['status' => 'completed']);
        Invoice::factory()->forJob($job)->create(['status' => 'paid']);

        $this->callTool('get_job', ['job_id' => $job->id], $this->user)
            ->assertOk()
            ->assertJsonPath('result.isError', false)
            ->assertJsonPath('result.structuredContent.id', $job->id)
            ->assertJsonPath('result.structuredContent.customer.name', $job->customer->name)
            ->assertJsonCount(1, 'result.structuredContent.invoices')
            ->assertJsonPath('result.structuredContent.invoices.0.status', 'paid');
    }

    public function test_get_job_not_found_is_a_tool_error_the_model_can_read(): void
    {
        $this->callTool('get_job', ['job_id' => 424242], $this->user)
            ->assertOk()
            ->assertJsonPath('result.isError', true)
            ->assertJsonPath('result.content.0.text', 'No job with id 424242 exists.');
    }

    public function test_missing_scope_is_refused_and_audited(): void
    {
        $this->callTool('revenue_summary', ['from' => '2026-01-01', 'to' => '2026-01-31'], $this->user, ['read:jobs'])
            ->assertOk()
            ->assertJsonPath('result.isError', true)
            ->assertJsonPath('result.content.0.text', 'This token does not carry the read:revenue scope required by revenue_summary.');

        $this->assertDatabaseHas('mcp_audit_logs', [
            'tool' => 'revenue_summary',
            'status' => 'denied_scope',
            'user_id' => $this->user->id,
        ]);
    }

    public function test_revenue_summary_groups_paid_invoices_by_month(): void
    {
        $job = ServiceJob::factory()->create(['status' => 'completed', 'technician' => 'A. Nguyen']);
        Invoice::factory()->for($job, 'serviceJob')->create(['status' => 'paid', 'amount_cents' => 10_000, 'issued_at' => '2026-03-02', 'paid_at' => '2026-03-10']);
        Invoice::factory()->for($job, 'serviceJob')->create(['status' => 'paid', 'amount_cents' => 5_000, 'issued_at' => '2026-03-20', 'paid_at' => '2026-04-02']);
        Invoice::factory()->for($job, 'serviceJob')->create(['status' => 'sent', 'amount_cents' => 7_000, 'issued_at' => '2026-04-05', 'paid_at' => null]);
        Invoice::factory()->for($job, 'serviceJob')->create(['status' => 'paid', 'amount_cents' => 99_000, 'issued_at' => '2025-12-01', 'paid_at' => '2025-12-15']);

        $response = $this->callTool('revenue_summary', ['from' => '2026-03-01', 'to' => '2026-04-30'], $this->user)
            ->assertOk()
            ->assertJsonPath('result.isError', false)
            ->assertJsonPath('result.structuredContent.total_cents', 15_000)
            ->assertJsonPath('result.structuredContent.invoice_count', 2)
            ->assertJsonPath('result.structuredContent.outstanding_cents', 7_000);

        $groups = collect($response->json('result.structuredContent.groups'))->keyBy('key');
        $this->assertSame(10_000, $groups['2026-03']['total_cents']);
        $this->assertSame(5_000, $groups['2026-04']['total_cents']);

        $this->callTool('revenue_summary', ['from' => '2026-03-01', 'to' => '2026-04-30', 'group_by' => 'technician'], $this->user)
            ->assertJsonPath('result.structuredContent.groups.0.key', 'A. Nguyen');
    }

    public function test_revenue_summary_rejects_inverted_or_oversized_ranges(): void
    {
        $this->callTool('revenue_summary', ['from' => '2026-05-01', 'to' => '2026-04-01'], $this->user)
            ->assertJsonPath('result.isError', true);

        $this->callTool('revenue_summary', ['from' => '2020-01-01', 'to' => '2026-01-01'], $this->user)
            ->assertJsonPath('result.isError', true);
    }

    public function test_write_tool_refuses_to_run_while_writes_are_disabled(): void
    {
        $job = ServiceJob::factory()->create(['status' => 'scheduled']);

        $this->callTool('update_job_status', ['job_id' => $job->id, 'status' => 'in_progress', 'confirm' => true], $this->user, ['write:jobs'])
            ->assertOk()
            ->assertJsonPath('result.isError', true)
            ->assertJsonPath('result.content.0.text', fn (string $text) => str_contains($text, 'disabled'));

        $this->assertSame('scheduled', $job->fresh()->status);
        $this->assertDatabaseHas('mcp_audit_logs', ['tool' => 'update_job_status', 'status' => 'denied_disabled']);
    }

    public function test_write_tool_requires_confirmation_flag(): void
    {
        config(['mcp.writes_enabled' => true]);
        $job = ServiceJob::factory()->create(['status' => 'scheduled']);

        $this->callTool('update_job_status', ['job_id' => $job->id, 'status' => 'in_progress'], $this->user, ['write:jobs'])
            ->assertOk()
            ->assertJsonPath('error.code', -32602)
            ->assertJsonPath('error.data.errors.0', 'arguments.confirm is required');

        $this->callTool('update_job_status', ['job_id' => $job->id, 'status' => 'in_progress', 'confirm' => false], $this->user, ['write:jobs'])
            ->assertOk()
            ->assertJsonPath('error.code', -32602);

        $this->assertSame('scheduled', $job->fresh()->status);
    }

    public function test_write_tool_applies_a_legal_transition_when_enabled(): void
    {
        config(['mcp.writes_enabled' => true]);
        $job = ServiceJob::factory()->create(['status' => 'in_progress', 'completed_at' => null]);

        $this->callTool('update_job_status', ['job_id' => $job->id, 'status' => 'completed', 'confirm' => true, 'reason' => 'Crew reported done'], $this->user, ['write:jobs'])
            ->assertOk()
            ->assertJsonPath('result.isError', false)
            ->assertJsonPath('result.structuredContent.changed.status.from', 'in_progress')
            ->assertJsonPath('result.structuredContent.changed.status.to', 'completed');

        $fresh = $job->fresh();
        $this->assertSame('completed', $fresh->status);
        $this->assertNotNull($fresh->completed_at);
    }

    public function test_write_tool_rejects_illegal_transition(): void
    {
        config(['mcp.writes_enabled' => true]);
        $job = ServiceJob::factory()->create(['status' => 'completed']);

        $this->callTool('update_job_status', ['job_id' => $job->id, 'status' => 'lead', 'confirm' => true], $this->user, ['write:jobs'])
            ->assertJsonPath('result.isError', true);

        $this->assertSame('completed', $job->fresh()->status);
    }

    public function test_successful_calls_are_audited_with_size_and_duration(): void
    {
        ServiceJob::factory()->count(2)->create();

        $this->callTool('list_jobs', ['limit' => 2], $this->user);

        $row = McpAuditLog::query()->where('tool', 'list_jobs')->firstOrFail();

        $this->assertSame('success', $row->status);
        $this->assertSame($this->user->id, $row->user_id);
        $this->assertSame(['limit' => 2], $row->arguments);
        $this->assertGreaterThan(100, $row->result_bytes);
        $this->assertNotEmpty($row->request_id);
    }

    public function test_arguments_are_not_stored_when_audit_logging_of_arguments_is_off(): void
    {
        config(['mcp.audit.log_arguments' => false]);
        ServiceJob::factory()->create();

        $this->callTool('list_jobs', ['search' => 'confidential term'], $this->user);

        $this->assertNull(McpAuditLog::query()->firstOrFail()->arguments);
    }
}
