<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * "Download the Pizi Owner app" reminders: their settings and who should get one.
 *
 * Owners who already have the app are told about a new version by the app itself (Google Play's in-app update
 * popup), so no message is sent from here for updates.
 */
class OwnerAppUpdateService
{
    // ── settings (admin panel) ───────────────────────────────────────────

    public function setting(string $key, $default = null)
    {
        $row = DB::table('app_settings')->where('key', $key)->first();

        return $row && $row->value !== null && $row->value !== '' ? $row->value : $default;
    }

    public function setSetting(string $key, $value): void
    {
        DB::table('app_settings')->updateOrInsert(
            ['key' => $key],
            ['value' => (string) $value, 'updated_at' => now(), 'created_at' => now()]
        );
    }

    // ── owners who never installed the app ───────────────────────────────

    /** Marker stored in owner_app_update_reminders.latest_version for "download the app" messages. */
    public const INSTALL_KIND = 'install';

    public function installEnabled(): bool
    {
        return (string) $this->setting('owner_install_reminders', '0') === '1';
    }

    /** No owner gets more than this many "download the app" messages in total. */
    public function installMax(): int
    {
        return max(1, (int) $this->setting('owner_install_max_reminders', 30));
    }

    /**
     * Active owners who have never signed in to the Android owner app (no Android session at all, even a signed-out
     * one) and have a usable phone number. Rebuilt on every call, so new owners join the list and an owner who
     * installs and signs in drops out by themselves.
     */
    public function installTargets(): \Illuminate\Support\Collection
    {
        return DB::table('users as u')
            ->where('u.role', 'owner')
            ->where('u.is_active', true)
            ->whereNotNull('u.phone')
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('api_sessions as s')
                ->whereColumn('s.user_id', 'u.id')->where('s.platform', 'android'))
            ->get(['u.id as user_id', 'u.name', 'u.phone'])
            ->filter(fn ($o) => strlen(preg_replace('/\D+/', '', (string) $o->phone)) >= 10)
            ->values();
    }

    /** Numbers for the admin page. */
    public function counts(): array
    {
        return [
            'no_app' => $this->installTargets()->count(),
            'install_sent_today' => (int) DB::table('owner_app_update_reminders')->where('latest_version', self::INSTALL_KIND)->whereDate('sent_at', today())->count(),
            'install_sent_total' => (int) DB::table('owner_app_update_reminders')->where('latest_version', self::INSTALL_KIND)->count(),
        ];
    }
}
