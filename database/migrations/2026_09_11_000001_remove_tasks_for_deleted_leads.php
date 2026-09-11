<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{

    public function up(): void
    {
        DB::table('tasks')
            ->where(function ($query): void {
                $query->whereExists(function ($leadQuery): void {
                    $leadQuery->select(DB::raw(1))
                        ->from('leads')
                        ->whereColumn('leads.id', 'tasks.lead_id')
                        ->whereNotNull('leads.deleted_at');
                })->orWhereNotExists(function ($leadQuery): void {
                    $leadQuery->select(DB::raw(1))
                        ->from('leads')
                        ->whereColumn('leads.id', 'tasks.lead_id');
                });
            })
            ->delete();
    }


    public function down(): void
    {

    }
};
