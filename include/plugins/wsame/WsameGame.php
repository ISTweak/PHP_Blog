<?php
declare(strict_types=1);

/**
 * ミニゲームの共通処理（ページ・結果・ランキング・ウィジェット）
 *
 * ゲームが終わると、JavaScript が結果を {path}/end に POST する。
 * 結果はいったんセッションに置き、ログインしていて今月のランキングに入るなら、
 * コメントを付けて {path}/save で記録できる。
 */
final class WsameGame
{
    /** ランキングに載せる件数 */
    public const RANK = 20;

    private string $t;
    private string $key;

    /**
     * @param array{title:string, path:string, stageLabel:string, maxScore:int, maxStage:int, order:int} $cfg
     */
    public function __construct(private PluginApi $api, public readonly array $cfg)
    {
        $this->t = $api->table('scores');
        $this->key = 'plg_' . $api->id . '_result';
    }

    public static function install(PluginApi $api): void
    {
        $t = $api->table('scores');
        $api->db()->exec("
            CREATE TABLE IF NOT EXISTS $t (
                id         INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id    INTEGER NOT NULL,
                score      INTEGER NOT NULL,
                stage      INTEGER NOT NULL DEFAULT 0,
                gametime   INTEGER NOT NULL DEFAULT 0,
                message    TEXT    NOT NULL DEFAULT '',
                created_at TEXT    NOT NULL
            );
            CREATE INDEX IF NOT EXISTS {$t}_score ON $t (score DESC, id);
            CREATE INDEX IF NOT EXISTS {$t}_date ON $t (created_at);
        ");
    }

    public function register(): void
    {
        $a = $this->api;
        $p = $this->cfg['path'];
        $a->navLink($this->cfg['title'], $p);
        $a->get($p, fn() => $this->game());
        $a->post($p . '/end', fn() => $this->end());
        $a->get($p . '/result', fn() => $this->result());
        $a->post($p . '/save', fn() => $this->save(), 'login');
        $a->get($p . '/ranking', fn() => $this->ranking());
        $a->widget($this->cfg['title'], fn() => $this->widget(), $this->cfg['order']);
    }

    /* ================= ページ ================= */

    private function game(): never
    {
        $this->api->addStyle('game.css');
        $this->api->addScript('game.js');
        $this->api->view('game', ['g' => $this], $this->cfg['title']);
    }

    /** ゲーム終了時に JavaScript から送られてくる結果 */
    private function end(): never
    {
        $int = static fn(string $k, int $max): int|false =>
            filter_var(input($k), FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => $max]]);
        $score = $int('score', $this->cfg['maxScore']);
        $stage = $int('stage', $this->cfg['maxStage']);
        $time = $int('gametime', 7 * 86400);
        if ($score === false || $stage === false || $time === false) {
            flash('error', '結果を受け取れませんでした。');
            redirect($this->cfg['path']);
        }
        $_SESSION[$this->key] = ['score' => $score, 'stage' => $stage, 'gametime' => $time];
        redirect($this->cfg['path'] . '/result');
    }

    private function result(): never
    {
        $r = $_SESSION[$this->key] ?? null;
        if (!is_array($r)) {
            redirect($this->cfg['path']);
        }
        $rank = $this->rankOf($r['score'], 'month');
        $this->api->addStyle('game.css');
        $this->api->view('result', [
            'g'       => $this,
            'r'       => $r,
            'rank'    => $rank,
            'rankAll' => $this->rankOf($r['score'], 'all'),
            'rankIn'  => $r['score'] > 0 && $rank <= self::RANK,
            'user'    => $this->api->user(),
            'rows'    => $this->top('month'),
        ], $this->cfg['title'] . 'の結果');
    }

    private function save(): never
    {
        $r = $_SESSION[$this->key] ?? null;
        if (!is_array($r)) {
            redirect($this->cfg['path'] . '/ranking');
        }
        if ($r['score'] <= 0 || $this->rankOf($r['score'], 'month') > self::RANK) {
            flash('error', '残念ながら、ランキングから外れてしまいました。');
            redirect($this->cfg['path'] . '/result');
        }
        $msg = trim(preg_replace('/\s+/u', ' ', input('message')) ?? '');
        $msg = mb_substr($msg, 0, 40);
        db_exec(
            "INSERT INTO {$this->t} (user_id, score, stage, gametime, message, created_at) VALUES (?, ?, ?, ?, ?, ?)",
            [$this->api->user()['id'], $r['score'], $r['stage'], $r['gametime'], $msg, now()]
        );
        unset($_SESSION[$this->key]);
        flash('success', 'ランキングに記録しました。');
        redirect($this->cfg['path'] . '/ranking');
    }

    private function ranking(): never
    {
        $period = query('period') === 'all' ? 'all' : 'month';
        $this->api->addStyle('game.css');
        $this->api->view('ranking', [
            'g'      => $this,
            'period' => $period,
            'rows'   => $this->top($period),
        ], $this->cfg['title'] . 'ランキング');
    }

    private function widget(): string
    {
        $this->api->addStyle('game.css');
        return $this->api->render('widget', ['g' => $this, 'rows' => $this->top('month', 3)]);
    }

    /* ================= ランキング ================= */

    public function top(string $period, int $limit = self::RANK): array
    {
        [$where, $params] = $this->period($period);
        return db_all(
            "SELECT s.*, COALESCE(NULLIF(u.display_name, ''), u.username, '（削除されたユーザー）') AS name
               FROM {$this->t} s LEFT JOIN users u ON u.id = s.user_id
              WHERE $where ORDER BY s.score DESC, s.id ASC LIMIT " . max(1, $limit),
            $params
        );
    }

    /** このスコアを今記録したときの順位（同点は先に記録した人が上） */
    private function rankOf(int $score, string $period): int
    {
        [$where, $params] = $this->period($period);
        return 1 + (int) db_val("SELECT COUNT(*) FROM {$this->t} s WHERE $where AND s.score >= ?", [...$params, $score]);
    }

    private function period(string $period): array
    {
        return $period === 'month' ? ['s.created_at >= ?', [date('Y-m-01 00:00:00')]] : ['1 = 1', []];
    }

    public static function time(int $sec): string
    {
        return $sec >= 3600
            ? sprintf('%d:%02d:%02d', intdiv($sec, 3600), intdiv($sec % 3600, 60), $sec % 60)
            : sprintf('%d:%02d', intdiv($sec, 60), $sec % 60);
    }

    public function url(string $sub = '', array $query = []): string
    {
        return $this->api->url($this->cfg['path'] . $sub, $query);
    }
}
