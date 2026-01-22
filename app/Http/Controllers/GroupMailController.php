<?php

namespace App\Http\Controllers;

use App\Mail\GroupNoticeMail;
use App\Models\{Group, GroupMailout, GroupMailRecipient, ProBowler};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;


class GroupMailController extends Controller
{
    /** 作成フォーム */
    public function create(Group $group)
    {
        $defaultFrom = config('mail.from.address');
        $defaultName = config('mail.from.name');

        return view('pro_groups.mail.create', [
            'group'=>$group,
            'defaults'=>['from_address'=>$defaultFrom,'from_name'=>$defaultName],
        ]);
    }

    /** 送信実行（作成+即送信） */
    public function store(Request $r, Group $group)
    {
        $data = $r->validate([
            'subject'      => 'required|string|max:200',
            'body'         => 'required|string',
            'from_address' => 'nullable|email',
            'from_name'    => 'nullable|string|max:100',
            'dry_run_to'   => 'nullable|email', // テスト送信用
        ]);

        // 対象メンバー（メールのある人だけ）
        $members = $group->members()
            ->select('pro_bowlers.id as bowler_id', 'pro_bowlers.name_kanji', 'pro_bowlers.email')
            ->whereNotNull('pro_bowlers.email')
            ->get();
        if ($members->isEmpty()) {
            return back()->withErrors('このグループにメールアドレスを持つメンバーがいません。');
        }

        /** テスト送信だけ（任意） */
        if ($data['dry_run_to'] ?? null) {
            $fakeMailout = new GroupMailout([
                'subject'=>$data['subject'],'body'=>$data['body'],
                'from_address'=>$data['from_address'],'from_name'=>$data['from_name'],
            ]);
            $first = $members->first();
            $fakeRec = new GroupMailRecipient(['email'=>$data['dry_run_to']]);
            Mail::to($data['dry_run_to'])->send(
                new GroupNoticeMail($fakeMailout, $fakeRec, ProBowler::find($first->bowler_id))
            );
            return back()->with('success','テストメールを送信しました（'.e($data['dry_run_to']).'）。');
        }

        // 本送信
        $mailout = DB::transaction(function() use ($group,$data,$members){
            $m = \App\Models\GroupMailout::create([
                'group_id'=>$group->id,
                'sender_user_id'=>auth()->id(),
                'subject'=>$data['subject'],
                'body'=>$data['body'],
                'from_address'=>$data['from_address'] ?: config('mail.from.address'),
                'from_name'=>$data['from_name'] ?: config('mail.from.name'),
                'status'=>'sending',
            ]);
            $rows = [];
            foreach ($members as $b) {
                $rows[] = [
                    'mailout_id'=>$m->id,
                    'pro_bowler_id'=>$b->bowler_id,
                    'email'=>$b->email,
                    'status'=>'queued',
                    'created_at'=>now(),'updated_at'=>now(),
                ];
            }
            \App\Models\GroupMailRecipient::insert($rows);
            return $m;
        });

        // 送信（開発中は sync でOK。将来は Queue に差し替え）
        $sent = 0; $fail = 0;
        GroupMailRecipient::where('mailout_id',$mailout->id)->chunkById(200, function($chunk) use ($mailout,&$sent,&$fail){
            foreach ($chunk as $rec) {
                $bowler = ProBowler::find($rec->pro_bowler_id);
                try {
                    Mail::to($rec->email)->send(new GroupNoticeMail($mailout, $rec, $bowler));
                    $rec->update(['status'=>'sent','sent_at'=>now()]);
                    $sent++;
                } catch (\Throwable $e) {
                    $rec->update(['status'=>'failed','error_message'=>substr($e->getMessage(),0,500)]);
                    $fail++;
                }
            }
        });

        $mailout->update([
            'status' => $fail ? 'failed' : 'sent',
            'sent_count'=>$sent, 'fail_count'=>$fail,
        ]);

        return redirect()->route('pro_groups.mail.show', [$group, $mailout])
            ->with('success', "送信完了: 成功 {$sent} 件 / 失敗 {$fail} 件");
    }

    /** 履歴表示 */
    public function show(Group $group, GroupMailout $mailout)
    {
        abort_unless($mailout->group_id === $group->id, 404);
        $recap = [
            'queued' => $mailout->recipients()->where('status','queued')->count(),
            'sent'   => $mailout->recipients()->where('status','sent')->count(),
            'failed' => $mailout->recipients()->where('status','failed')->count(),
        ];
        $samples = $mailout->recipients()->with('bowler')->latest()->take(50)->get();
        return view('pro_groups.mail.show', compact('group','mailout','recap','samples'));
    }
}
