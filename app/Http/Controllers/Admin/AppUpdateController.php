<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\OwnerAppUpdateService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Admin control of the app WhatsApp messages: "download the owner app" and the tenant app invite. */
class AppUpdateController extends Controller
{
    public function __construct(private OwnerAppUpdateService $updates)
    {
    }

    public function index()
    {
        return view('admin.app-updates', [
            'counts' => $this->updates->counts(),
            'tenantInviteOn' => \App\Services\TenantAppInviteService::enabled(),
            'tenantInvitesSent' => (int) DB::table('tenant_app_invites')->where('status', 'sent')->count(),
            'tenantInvitesToday' => (int) DB::table('tenant_app_invites')->where('status', 'sent')->whereDate('sent_at', today())->count(),
            'tenantBackfillOn' => \App\Services\TenantAppInviteService::backfillEnabled(),
            'tenantBatch' => \App\Services\TenantAppInviteService::batchSize(),
            'tenantsPending' => app(\App\Services\TenantAppInviteService::class)->pendingExisting()->count(),
            'installOn' => $this->updates->installEnabled(),
            'installMax' => $this->updates->installMax(),
            'noApp' => $this->updates->installTargets()->take(25),
            'installRecent' => DB::table('owner_app_update_reminders as r')
                ->join('users as u', 'u.id', '=', 'r.user_id')
                ->where('r.latest_version', OwnerAppUpdateService::INSTALL_KIND)
                ->orderByDesc('r.id')->limit(20)
                ->get(['u.name', 'u.phone', 'r.sent_at']),
        ]);
    }

    public function saveTenantInvite(Request $request)
    {
        $data = $request->validate(['tenant_batch' => 'required|integer|min:1|max:2000']);
        $on = $request->boolean('tenant_invite_enabled');
        $backfill = $request->boolean('tenant_invite_backfill');
        \App\Services\TenantAppInviteService::setEnabled($on);
        \App\Services\TenantAppInviteService::setBackfillEnabled($backfill);
        \App\Services\TenantAppInviteService::setBatchSize((int) $data['tenant_batch']);

        if (!$on) {
            return back()->with('success', 'Saved. Nobody is messaged' . ($backfill ? ' (the batch for earlier tenants also needs "Message new tenants" ticked).' : '.'));
        }

        return back()->with('success', 'Saved. Every tenant an owner adds gets a WhatsApp with the tenant app download link (once per phone number)'
            . ($backfill ? ', and tenants added earlier get it in daily batches of ' . $data['tenant_batch'] . '.' : '.'));
    }

    public function saveInstall(Request $request)
    {
        $data = $request->validate(['install_max' => 'required|integer|min:1|max:365']);
        $on = $request->boolean('install_enabled');

        $this->updates->setSetting('owner_install_max_reminders', $data['install_max']);
        $this->updates->setSetting('owner_install_reminders', $on ? '1' : '0');

        return back()->with('success', $on
            ? 'Saved. Owners who have not installed the app get a WhatsApp message every day until they install and sign in.'
            : 'Saved. The download reminders are OFF: nobody is messaged.');
    }
}
