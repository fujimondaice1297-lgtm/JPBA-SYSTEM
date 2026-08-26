<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProBowler;
use App\Models\User;
use App\Services\PlayerAccountService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;
use InvalidArgumentException;

class PlayerAccountAdminController extends Controller
{
    public function show(ProBowler $bowler): View
    {
        $bowler->load(['userAccount.accountStatusLogs.changedByUser']);

        return view('admin.player_accounts.show', [
            'bowler' => $bowler,
            'account' => $bowler->userAccount,
        ]);
    }

    public function issue(
        ProBowler $bowler,
        PlayerAccountService $accounts
    ): RedirectResponse {
        $result = $accounts->issue($bowler, changedBy: auth()->id());

        return back()->with(
            $result['status'] === 'skipped' ? 'error' : 'success',
            $result['message']
        );
    }

    public function sendSetupLink(
        ProBowler $bowler,
        PlayerAccountService $accounts
    ): RedirectResponse {
        $account = $bowler->userAccount;
        if (! $account) {
            return back()->with('error', '先に選手アカウントを発行してください。');
        }

        try {
            $status = $accounts->sendSetupLink($account);
        } catch (InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return $status === Password::RESET_LINK_SENT
            ? back()->with('success', '初回パスワード設定メールを送信しました。')
            : back()->with('error', 'メールを送信できませんでした: '.__($status));
    }

    public function updateStatus(
        Request $request,
        ProBowler $bowler,
        PlayerAccountService $accounts
    ): RedirectResponse {
        $validated = $request->validate([
            'account_status' => ['required', 'in:active,suspended,closed'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);
        $account = $bowler->userAccount;
        if (! $account) {
            return back()->with('error', '選手アカウントが発行されていません。');
        }

        try {
            $accounts->changeStatus(
                $account,
                $validated['account_status'],
                $validated['reason'] ?? null,
                auth()->id(),
            );
        } catch (InvalidArgumentException $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        $label = match ($validated['account_status']) {
            User::STATUS_ACTIVE => '利用中',
            User::STATUS_SUSPENDED => '利用停止',
            User::STATUS_CLOSED => '終了',
        };

        return back()->with('success', "アカウント状態を「{$label}」へ変更しました。");
    }
}
