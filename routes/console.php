<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('mcp:audit:prune')->daily();
