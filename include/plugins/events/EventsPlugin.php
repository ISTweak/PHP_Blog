<?php
declare(strict_types=1);

final class EventsPlugin
{
    public const STATUSES = ['yes' => '参加', 'maybe' => '未定', 'no' => '不参加'];

    private string $events;
    private string $responses;

    public function __construct(private PluginApi $api)
    {
        $this->events = $api->table('events');
        $this->responses = $api->table('responses');
    }

    public function register(): void
    {
        $api = $this->api;
        $api->navLink('イベント', '/events');
        $api->get('/events', fn() => $this->index());
        $api->get('/events/{id}', fn(string $id) => $this->show($id));
        $api->post('/events/{id}/respond', fn(string $id) => $this->respond($id));
        $api->post('/events/{id}/cancel', fn(string $id) => $this->cancel($id));
        $api->adminPage('', 'イベント', fn() => $this->admin(), 'editor');
        $api->widget('今後のイベント', fn() => $this->widget(), 20);
    }

    /* ---------------- 閲覧側 ---------------- */

    private function index(): void
    {
        $now = date('Y-m-d H:i');
        $this->api->addStyle('events.css');
        $this->api->view('list', [
            'upcoming' => $this->withCounts(db_all("SELECT * FROM {$this->events} WHERE starts_at >= ? ORDER BY starts_at ASC", [$now])),
            'past'     => $this->withCounts(db_all("SELECT * FROM {$this->events} WHERE starts_at < ? ORDER BY starts_at DESC LIMIT 20", [$now])),
        ], 'イベント');
    }

    private function show(string $id): void
    {
        $event = $this->find($id);
        $responses = db_all(
            "SELECT * FROM {$this->responses} WHERE event_id = ?
              ORDER BY CASE status WHEN 'yes' THEN 0 WHEN 'maybe' THEN 1 ELSE 2 END, updated_at",
            [$event['id']]
        );
        $this->api->addStyle('events.css');
        $this->api->view('detail', [
            'event'     => $this->withCounts([$event])[0],
            'responses' => $responses,
            'mine'      => $this->myResponse((int) $event['id']),
            'closed'    => $this->isClosed($event),
        ], $event['title']);
    }

    private function respond(string $id): void
    {
        $event = $this->find($id);
        $back = '/events/' . (int) $event['id'];
        if ($this->isClosed($event)) {
            flash('error', '回答の受付は締め切りました。');
            redirect($back);
        }
        $status = input('status');
        $comment = mb_substr(str_replace(["\r\n", "\r", "\n"], ' ', input('comment')), 0, 200);
        if (!isset(self::STATUSES[$status])) {
            flash('error', '参加・未定・不参加のどれかを選んでください。');
            redirect($back);
        }

        $user = $this->api->user();
        $now = now();
        $mine = $this->myResponse((int) $event['id']);
        if ($user) {
            $name = (string) $user['display_name'];
        } else {
            $name = mb_substr(input('name'), 0, 50);
            if ($name === '') {
                flash('error', 'お名前を入力してください。');
                with_old(['name' => $name, 'comment' => $comment, 'status' => $status]);
                redirect($back);
            }
        }

        if ($mine) {
            db_exec("UPDATE {$this->responses} SET name = ?, status = ?, comment = ?, updated_at = ? WHERE id = ?",
                [$name, $status, $comment, $now, $mine['id']]);
        } else {
            db_exec(
                "INSERT INTO {$this->responses} (event_id, user_id, guest_token, name, status, comment, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                [$event['id'], $user['id'] ?? null, $user ? null : $this->guestToken(), $name, $status, $comment, $now, $now]
            );
        }
        flash('success', '「' . self::STATUSES[$status] . '」で回答しました。');
        redirect($back);
    }

    private function cancel(string $id): void
    {
        $event = $this->find($id);
        $mine = $this->myResponse((int) $event['id']);
        if ($mine && !$this->isClosed($event)) {
            db_exec("DELETE FROM {$this->responses} WHERE id = ?", [$mine['id']]);
            flash('success', '回答を取り消しました。');
        }
        redirect('/events/' . (int) $event['id']);
    }

    private function widget(): string
    {
        $events = db_all(
            "SELECT * FROM {$this->events} WHERE starts_at >= ? ORDER BY starts_at ASC LIMIT 3",
            [date('Y-m-d H:i')]
        );
        if (!$events) {
            return '';
        }
        $this->api->addStyle('events.css');
        return $this->api->render('widget', ['events' => $this->withCounts($events)]);
    }

    /* ---------------- 管理画面 ---------------- */

    private function admin(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $this->adminPost();
        }
        $editId = (int) query('edit');
        $editing = $editId > 0 ? db_one("SELECT * FROM {$this->events} WHERE id = ?", [$editId]) : null;
        $events = $this->withCounts(db_all("SELECT * FROM {$this->events} ORDER BY starts_at DESC LIMIT 200"));
        $this->api->adminView('admin', [
            'events'  => $events,
            'editing' => $editing,
        ], 'イベント');
    }

    private function adminPost(): never
    {
        $id = (int) input('id');
        if (input('action') === 'delete') {
            $event = db_one("SELECT * FROM {$this->events} WHERE id = ?", [$id]) ?? abort(404);
            db_exec("DELETE FROM {$this->responses} WHERE event_id = ?", [$id]);
            db_exec("DELETE FROM {$this->events} WHERE id = ?", [$id]);
            flash('success', '「' . $event['title'] . '」を削除しました。');
            header('Location: ' . $this->api->adminUrl(), true, 303);
            exit;
        }

        $title = mb_substr(input('title'), 0, 100);
        $place = mb_substr(input('place'), 0, 100);
        $description = mb_substr(str_replace(["\r\n", "\r"], "\n", input('description')), 0, 5000);
        $startsAt = $this->parseDateTime(input('starts_at'));
        $deadline = input('deadline') !== '' ? $this->parseDateTime(input('deadline')) : null;
        $errors = [];
        if ($title === '') {
            $errors[] = 'イベント名を入力してください。';
        }
        if ($startsAt === null) {
            $errors[] = '開催日時を正しく入力してください。';
        }
        if (input('deadline') !== '' && $deadline === null) {
            $errors[] = '回答締切を正しく入力してください。';
        }
        if ($errors) {
            foreach ($errors as $err) {
                flash('error', $err);
            }
            header('Location: ' . $this->api->adminUrl('', $id ? ['edit' => $id] : []), true, 303);
            exit;
        }

        $now = now();
        if ($id > 0) {
            db_exec(
                "UPDATE {$this->events} SET title = ?, starts_at = ?, place = ?, description = ?, deadline = ?, updated_at = ? WHERE id = ?",
                [$title, $startsAt, $place, $description, $deadline, $now, $id]
            );
            flash('success', '「' . $title . '」を更新しました。');
        } else {
            db_exec(
                "INSERT INTO {$this->events} (title, starts_at, place, description, deadline, created_by, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                [$title, $startsAt, $place, $description, $deadline, $this->api->user()['id'] ?? null, $now, $now]
            );
            flash('success', '「' . $title . '」を登録しました。');
        }
        header('Location: ' . $this->api->adminUrl(), true, 303);
        exit;
    }

    /* ---------------- 共通 ---------------- */

    private function find(string $id): array
    {
        return db_one("SELECT * FROM {$this->events} WHERE id = ?", [(int) $id]) ?? abort(404);
    }

    private function isClosed(array $event): bool
    {
        $limit = $event['deadline'] ?: $event['starts_at'];
        return date('Y-m-d H:i') > $limit;
    }

    /** 各イベントに参加・未定・不参加の人数を付ける */
    private function withCounts(array $events): array
    {
        if (!$events) {
            return [];
        }
        $ids = array_map(static fn($e) => (int) $e['id'], $events);
        $in = implode(',', array_fill(0, count($ids), '?'));
        $counts = [];
        foreach (db_all("SELECT event_id, status, COUNT(*) AS n FROM {$this->responses} WHERE event_id IN ($in) GROUP BY event_id, status", $ids) as $r) {
            $counts[(int) $r['event_id']][$r['status']] = (int) $r['n'];
        }
        foreach ($events as &$e) {
            $e['counts'] = ($counts[(int) $e['id']] ?? []) + ['yes' => 0, 'maybe' => 0, 'no' => 0];
        }
        return $events;
    }

    /** ログイン中ならユーザー、ゲストならブラウザごとのトークンで自分の回答を探す */
    private function myResponse(int $eventId): ?array
    {
        $user = $this->api->user();
        if ($user) {
            return db_one("SELECT * FROM {$this->responses} WHERE event_id = ? AND user_id = ?", [$eventId, $user['id']]);
        }
        $token = $_SESSION['plg_events_token'] ?? null;
        return $token ? db_one("SELECT * FROM {$this->responses} WHERE event_id = ? AND guest_token = ?", [$eventId, $token]) : null;
    }

    private function guestToken(): string
    {
        return $_SESSION['plg_events_token'] ??= bin2hex(random_bytes(16));
    }

    /** "2026-12-01 18:00" → "2026年12月1日(火) 18:00" */
    public static function formatDate(?string $value, bool $withTime = true): string
    {
        $ts = $value ? strtotime($value) : false;
        if (!$ts) {
            return '';
        }
        $week = ['日', '月', '火', '水', '木', '金', '土'][(int) date('w', $ts)];
        return date('Y年n月j日', $ts) . "($week)" . ($withTime ? date(' H:i', $ts) : '');
    }

    /** datetime-local の値（2026-12-01T18:00）を "2026-12-01 18:00" に */
    private function parseDateTime(string $value): ?string
    {
        $d = DateTime::createFromFormat('Y-m-d\TH:i', $value) ?: DateTime::createFromFormat('Y-m-d H:i', $value);
        return $d ? $d->format('Y-m-d H:i') : null;
    }
}
