<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\AuditLog;
use Tests\TestCase;

class HistoryResetTest extends TestCase
{
    public function test_admin_can_clear_activity_history_for_the_current_tenant(): void
    {
        $this->actingAsAdmin();
        $lead = $this->createLead();

        $activity = Activity::create([
            'tenant_id' => $this->tenant->id,
            'lead_id' => $lead->id,
            'agent_id' => $this->adminUser->id,
            'type' => 'note',
            'subject' => 'Old test activity',
            'logged_at' => now(),
        ]);

        $this->delete(route('activities.clear'))
            ->assertRedirect(route('activities.index'));

        $this->assertDatabaseMissing('activities', ['id' => $activity->id]);
    }

    public function test_admin_can_clear_audit_history_for_the_current_tenant(): void
    {
        $this->actingAsAdmin();

        $audit = AuditLog::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->adminUser->id,
            'action' => 'old_test.action',
        ]);

        $this->delete(route('audit-log.clear'))
            ->assertRedirect(route('audit-log.index'));

        $this->assertDatabaseMissing('audit_log', ['id' => $audit->id]);
    }
}
