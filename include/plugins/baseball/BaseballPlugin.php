<?php
declare(strict_types=1);

final class BaseballPlugin
{
    public const STATUSES = [
        'scheduled' => '予定',
        'final'     => '試合終了',
        'postponed' => '順延',
        'cancelled' => '中止',
    ];
    public const RESULTS = ['win' => '勝', 'lose' => '負', 'draw' => '分'];

    private string $t;

    public function __construct(private PluginApi $api)
    {
        $this->t = $api->table('games');
    }

    public function register(): void
    {
        $a = $this->api;
        $a->navLink('試合日程', '/baseball');
        $a->get('/baseball', fn() => $this->index());
        $a->adminPage('', '試合日程・結果', fn() => $this->admin(), 'author');
        $a->widget('次の試合', fn() => $this->widgetNext(), 30);
        $a->widget('試合結果', fn() => $this->widgetResults(), 31);
    }

    /* ================= 閲覧側 ================= */

    private function index(): void
    {
        $years = $this->years();
        $year = (int) query('y', date('Y'));
        if (!in_array($year, $years, true)) {
            $year = (int) date('Y');
        }
        $upcoming = $this->upcoming();
        $results = db_all(
            "SELECT * FROM {$this->t}
              WHERE strftime('%Y', game_date) = ? AND (status IN ('final', 'cancelled') OR game_date < ?)
              ORDER BY game_date DESC, start_time DESC, id DESC",
            [(string) $year, date('Y-m-d')]
        );
        $this->api->addStyle('baseball.css');
        $this->api->view('index', [
            'team'     => $this->team(),
            'next'     => $upcoming[0] ?? null,
            'upcoming' => array_slice($upcoming, 1),
            'results'  => $results,
            'record'   => $this->record($year),
            'year'     => $year,
            'years'    => $years,
            'posts'    => $this->postTitles(array_merge($upcoming, $results)),
        ], '試合日程・結果');
    }

    /** サイドバー「次の試合」（試合が無くてもブロックは表示する） */
    private function widgetNext(): string
    {
        $this->api->addStyle('baseball.css');
        return $this->api->render('widget-next', [
            'team' => $this->team(),
            'next' => $this->upcoming()[0] ?? null,
        ]);
    }

    /** サイドバー「試合結果」：直近 3 試合と今年の成績 */
    private function widgetResults(): string
    {
        $this->api->addStyle('baseball.css');
        return $this->api->render('widget-results', [
            'recent' => db_all("SELECT * FROM {$this->t} WHERE status = 'final' ORDER BY game_date DESC, start_time DESC, id DESC LIMIT 3"),
            'record' => $this->record((int) date('Y')),
            'year'   => (int) date('Y'),
        ]);
    }

    /* ================= 管理画面 ================= */

    private function admin(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $this->adminPost();
        }
        $years = $this->years();
        $year = (int) query('y', date('Y'));
        if (!in_array($year, $years, true)) {
            $year = (int) date('Y');
        }
        $editId = (int) query('edit');
        $this->api->addStyle('baseball.css');
        $this->api->addScript('baseball-admin.js');
        $this->api->adminView('admin', [
            'opponents'    => array_column(db_all("SELECT opponent v FROM {$this->t} GROUP BY opponent ORDER BY MAX(game_date) DESC LIMIT 50"), 'v'),
            'competitions' => array_column(db_all("SELECT competition v FROM {$this->t} WHERE competition <> '' GROUP BY competition ORDER BY MAX(game_date) DESC LIMIT 30"), 'v'),
            'venues'       => array_column(db_all("SELECT venue v FROM {$this->t} WHERE venue <> '' GROUP BY venue ORDER BY MAX(game_date) DESC LIMIT 50"), 'v'),
            'team'    => $this->team(),
            'games'   => db_all("SELECT * FROM {$this->t} WHERE strftime('%Y', game_date) = ? ORDER BY game_date DESC, start_time DESC, id DESC", [(string) $year]),
            'editing' => $editId > 0 ? db_one("SELECT * FROM {$this->t} WHERE id = ?", [$editId]) : null,
            'year'    => $year,
            'years'   => $years,
            'record'  => $this->record($year),
            'posts'   => db_all('SELECT id, title, published_at FROM posts ORDER BY published_at DESC, id DESC LIMIT 100'),
            'canTeam' => $this->api->can('editor'),
        ], '試合日程・結果');
    }

    private function adminPost(): never
    {
        $action = input('action');
        $id = (int) input('id');
        $back = $this->api->adminUrl('', array_filter(['y' => input('y')]));

        if ($action === 'team') {
            if (!$this->api->can('editor')) {
                abort(403);
            }
            $name = mb_substr(input('team'), 0, 50);
            $this->api->saveSetting('team', $name);
            flash('success', 'チーム名を保存しました。');
            $this->go($back);
        }

        if ($action === 'delete') {
            $g = db_one("SELECT * FROM {$this->t} WHERE id = ?", [$id]) ?? abort(404);
            db_exec("DELETE FROM {$this->t} WHERE id = ?", [$id]);
            flash('success', fmt_date($g['game_date'], 'n/j') . ' vs ' . $g['opponent'] . ' を削除しました。');
            $this->go($back);
        }

        [$d, $errors] = $this->validate();
        if ($errors) {
            foreach ($errors as $err) {
                flash('error', $err);
            }
            with_old($_POST);
            $this->go($this->api->adminUrl('', array_filter(['edit' => $id ?: null, 'y' => input('y'), 'new' => $id ? null : 1])));
        }
        $now = now();
        if ($id > 0) {
            db_exec(
                "UPDATE {$this->t} SET game_date = ?, start_time = ?, opponent = ?, venue = ?, competition = ?, status = ?,
                    our_score = ?, opp_score = ?, comment = ?, post_id = ?, updated_at = ? WHERE id = ?",
                [$d['game_date'], $d['start_time'], $d['opponent'], $d['venue'], $d['competition'], $d['status'],
                 $d['our_score'], $d['opp_score'], $d['comment'], $d['post_id'], $now, $id]
            );
            flash('success', '試合を更新しました。');
        } else {
            db_exec(
                "INSERT INTO {$this->t} (game_date, start_time, opponent, venue, competition, status, our_score, opp_score, comment, post_id,
                    created_by, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [$d['game_date'], $d['start_time'], $d['opponent'], $d['venue'], $d['competition'], $d['status'],
                 $d['our_score'], $d['opp_score'], $d['comment'], $d['post_id'], $this->api->user()['id'] ?? null, $now, $now]
            );
            flash('success', '試合を登録しました。');
        }
        $this->go($this->api->adminUrl('', ['y' => substr($d['game_date'], 0, 4)]));
    }

    private function validate(): array
    {
        $d = [
            'game_date'   => input('game_date'),
            'start_time'  => input('start_time'),
            'opponent'    => mb_substr(input('opponent'), 0, 60),
            'venue'       => mb_substr(input('venue'), 0, 100),
            'competition' => mb_substr(input('competition'), 0, 60),
            'status'      => input('status'),
            'our_score'   => input('our_score') === '' ? null : (int) input('our_score'),
            'opp_score'   => input('opp_score') === '' ? null : (int) input('opp_score'),
            'comment'     => mb_substr(str_replace(["\r\n", "\r"], "\n", input('comment')), 0, 2000),
            'post_id'     => (int) input('post_id') ?: null,
        ];
        $errors = [];
        $date = DateTime::createFromFormat('!Y-m-d', $d['game_date']);
        if (!$date || $date->format('Y-m-d') !== $d['game_date']) {
            $errors[] = '日付を正しく入力してください。';
        }
        if ($d['start_time'] !== '' && !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $d['start_time'])) {
            $errors[] = '開始時刻を正しく入力してください。';
        }
        if ($d['opponent'] === '') {
            $errors[] = '対戦相手を入力してください。';
        }
        if (!isset(self::STATUSES[$d['status']])) {
            $errors[] = '状態を選んでください。';
        }
        foreach (['our_score', 'opp_score'] as $k) {
            if ($d[$k] !== null && ($d[$k] < 0 || $d[$k] > 99)) {
                $errors[] = '得点は 0〜99 で入力してください。';
                break;
            }
        }
        if ($d['status'] === 'final' && ($d['our_score'] === null || $d['opp_score'] === null)) {
            $errors[] = '「試合終了」にするときは、両チームの得点を入力してください。';
        }
        if ($d['status'] !== 'final') {
            // 試合終了以外は得点を持たない（途中で中止になった試合などはコメントに書く）
            $d['our_score'] = $d['opp_score'] = null;
        }
        if ($d['post_id'] !== null && !db_val('SELECT 1 FROM posts WHERE id = ?', [$d['post_id']])) {
            $d['post_id'] = null;
        }
        return [$d, $errors];
    }

    /* ================= 共通 ================= */

    public function team(): string
    {
        return $this->api->setting('team', '自チーム');
    }

    /** 今日以降の予定（順延を含む） */
    private function upcoming(): array
    {
        return db_all(
            "SELECT * FROM {$this->t} WHERE status IN ('scheduled', 'postponed') AND game_date >= ?
              ORDER BY game_date, CASE WHEN start_time = '' THEN '99:99' ELSE start_time END, id",
            [date('Y-m-d')]
        );
    }

    /** その年の成績 */
    private function record(int $year): array
    {
        $r = ['win' => 0, 'lose' => 0, 'draw' => 0];
        foreach (db_all("SELECT our_score, opp_score FROM {$this->t} WHERE status = 'final' AND strftime('%Y', game_date) = ?", [(string) $year]) as $g) {
            $r[self::result($g)]++;
        }
        $decided = $r['win'] + $r['lose'];
        $r['games'] = $decided + $r['draw'];
        $r['rate'] = $decided > 0 ? $r['win'] / $decided : null;   // 勝率は引き分けを除いて計算
        return $r;
    }

    /** 選択肢に出す年（試合がある年＋今年） */
    private function years(): array
    {
        $years = array_map('intval', array_column(db_all("SELECT DISTINCT strftime('%Y', game_date) y FROM {$this->t}"), 'y'));
        $years[] = (int) date('Y');
        $years = array_values(array_unique($years));
        rsort($years);
        return $years;
    }

    private function postTitles(array $games): array
    {
        $ids = array_values(array_unique(array_filter(array_map(static fn($g) => (int) $g['post_id'], $games))));
        if (!$ids) {
            return [];
        }
        $in = implode(',', array_fill(0, count($ids), '?'));
        return array_column(db_all("SELECT id, title FROM posts WHERE id IN ($in)", $ids), 'title', 'id');
    }

    private function go(string $url): never
    {
        header('Location: ' . $url, true, 303);
        exit;
    }

    public static function result(array $g): ?string
    {
        if ($g['our_score'] === null || $g['opp_score'] === null) {
            return null;
        }
        return $g['our_score'] > $g['opp_score'] ? 'win' : ($g['our_score'] < $g['opp_score'] ? 'lose' : 'draw');
    }

    /** "2026-12-06" → "12/6(日)" */
    public static function day(string $ymd, bool $withYear = false): string
    {
        $ts = strtotime($ymd);
        if (!$ts) {
            return '';
        }
        return date($withYear ? 'Y/n/j' : 'n/j', $ts) . '(' . ['日', '月', '火', '水', '木', '金', '土'][(int) date('w', $ts)] . ')';
    }

    public static function rate(?float $rate): string
    {
        return $rate === null ? '-' : ltrim(number_format($rate, 3), '0');
    }
}
