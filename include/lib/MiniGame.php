<?php
declare(strict_types=1);

/**
 * ミニゲームのプラグイン用の共通処理（ゲームのページ・結果・ランキング・サイドバー）
 *
 * ゲームが終わると、JavaScript が結果を {path}/end に POST する。
 * 結果はいったんセッションに置き、今月のランキングに入るなら {path}/save で記録できる。
 * ログインしていない人は名前を入れて記録する（会員限定サイトでは全員ログインしている）。
 *
 * プラグイン側で用意するもの:
 *   views/game.php      ゲームの画面（結果は score / stage / gametime を {path}/end へ POST）
 *   assets/game.js      ゲーム本体
 *   assets/game.css     ゲームの見た目（任意）
 *
 * 使い方（plugin.php）:
 *   'install' => fn(PluginApi $api) => MiniGame::install($api),
 *   'boot'    => fn(PluginApi $api) => (new MiniGame($api, ['title' => '2048', 'path' => '/games/2048']))->register(),
 */
final class MiniGame
{
    /** ランキングに載せる件数 */
    public const RANK = 20;
    /** ゲストの名前の最大文字数 */
    public const NAME_MAX = 20;
    private const SCHEMA = '2';

    private string $t;
    private string $key;
    /** @var array<string, mixed> */
    public readonly array $cfg;

    /**
     * @param array<string, mixed> $cfg
     *   title       ゲーム名（ヘッダーのメニュー・ウィジェットの見出しにも使う）
     *   path        ゲームの URL（例 /games/2048）
     *   order       ウィジェットの並び順の初期値
     *   scoreLabel  スコアの見出し（初期値「スコア」）
     *   unit        スコアの単位（初期値「点」）
     *   lowerIsBetter  小さいほど上位（手数など）。同じ値ならプレイ時間の短い方が上
     *   stageLabel  stage 列の見出し（null なら表示しない）
     *   variants    種類ごとに別のランキングにする場合 ['4' => '4×4', ...]（stage に種類を入れる）
     *   defaultVariant  ウィジェットなどで使う種類
     *   maxScore / maxStage  受け付ける値の上限
     */
    public function __construct(private PluginApi $api, array $cfg)
    {
        $this->cfg = $cfg + [
            'order' => 90, 'scoreLabel' => 'スコア', 'unit' => '点', 'lowerIsBetter' => false,
            'stageLabel' => null, 'variants' => [], 'defaultVariant' => null,
            'maxScore' => 99_999_999, 'maxStage' => 99_999_999,
        ];
        $this->t = $api->table('scores');
        $this->key = 'plg_' . $api->id . '_result';
    }

    /** テーブルの作成（古い形のテーブルは作り直す） */
    public static function install(PluginApi $api): void
    {
        $t = $api->table('scores');
        $pdo = $api->db();
        $cols = array_column($pdo->query("PRAGMA table_info($t)")->fetchAll(PDO::FETCH_ASSOC), null, 'name');
        if ($cols && !isset($cols['name'])) {
            // 1.0.0 のテーブル（user_id 必須・名前の列なし）からの移行
            $pdo->exec("ALTER TABLE $t RENAME TO {$t}_old");
            $pdo->exec("DROP INDEX IF EXISTS {$t}_score");
            $pdo->exec("DROP INDEX IF EXISTS {$t}_date");
        }
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS $t (
                id         INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id    INTEGER,
                name       TEXT    NOT NULL DEFAULT '',
                score      INTEGER NOT NULL,
                stage      INTEGER NOT NULL DEFAULT 0,
                gametime   INTEGER NOT NULL DEFAULT 0,
                message    TEXT    NOT NULL DEFAULT '',
                created_at TEXT    NOT NULL
            );
            CREATE INDEX IF NOT EXISTS {$t}_score ON $t (score, id);
            CREATE INDEX IF NOT EXISTS {$t}_date ON $t (created_at);
        ");
        if ($cols && !isset($cols['name'])) {
            $pdo->exec("INSERT INTO $t (id, user_id, score, stage, gametime, message, created_at)
                        SELECT id, user_id, score, stage, gametime, message, created_at FROM {$t}_old");
            $pdo->exec("DROP TABLE {$t}_old");
        }
        $api->saveSetting('schema', self::SCHEMA);
    }

    public function register(): void
    {
        $a = $this->api;
        if ($a->setting('schema') !== self::SCHEMA) {
            self::install($a);
        }
        $p = $this->cfg['path'];
        $a->navLink($this->cfg['title'], $p);
        $a->get($p, fn() => $this->game());
        $a->post($p . '/end', fn() => $this->end());
        $a->get($p . '/result', fn() => $this->result());
        $a->post($p . '/save', fn() => $this->save());
        $a->get($p . '/ranking', fn() => $this->ranking());
        $a->widget($this->cfg['title'], fn() => $this->widget(), $this->cfg['order']);
    }

    /* ================= ページ ================= */

    private function game(): never
    {
        $this->styles();
        $this->api->addScript('game.js');
        $this->api->view('game', ['g' => $this], $this->cfg['title']);
    }

    /** ゲーム終了時に JavaScript から送られてくる結果 */
    private function end(): never
    {
        $int = static fn(string $k, int $min, int $max): int|false =>
            filter_var(input($k), FILTER_VALIDATE_INT, ['options' => ['min_range' => $min, 'max_range' => $max]]);
        $score = $int('score', 0, $this->cfg['maxScore']);
        $stage = $int('stage', 0, $this->cfg['maxStage']);
        $time = $int('gametime', 0, 7 * 86400);
        if ($score === false || $stage === false || $time === false
            || ($this->cfg['variants'] && !isset($this->cfg['variants'][(string) $stage]))) {
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
        $variant = $this->cfg['variants'] ? (string) $r['stage'] : null;
        $rank = $this->rankOf($r, 'month');
        $this->page('result', [
            'r'         => $r,
            'variant'   => $variant,
            'rank'      => $rank,
            'rankAll'   => $this->rankOf($r, 'all'),
            'rankIn'    => $this->rankable($r) && $rank <= self::RANK,
            'user'      => $this->api->user(),
            'guestName' => (string) ($_SESSION['minigame_name'] ?? ''),
            'rows'      => $this->top('month', $variant),
        ], $this->cfg['title'] . 'の結果');
    }

    private function save(): never
    {
        $r = $_SESSION[$this->key] ?? null;
        if (!is_array($r)) {
            redirect($this->cfg['path'] . '/ranking');
        }
        $user = $this->api->user();
        $name = '';
        if (!$user) {
            $name = mb_substr(trim(preg_replace('/\s+/u', ' ', input('name')) ?? ''), 0, self::NAME_MAX);
            if ($name === '') {
                flash('error', '名前を入力してください。');
                with_old($_POST);
                redirect($this->cfg['path'] . '/result');
            }
            $_SESSION['minigame_name'] = $name;   // 次に記録するときの入力の初期値
        }
        if (!$this->rankable($r) || $this->rankOf($r, 'month') > self::RANK) {
            flash('error', '残念ながら、ランキングから外れてしまいました。');
            redirect($this->cfg['path'] . '/result');
        }
        $msg = mb_substr(trim(preg_replace('/\s+/u', ' ', input('message')) ?? ''), 0, 40);
        db_exec(
            "INSERT INTO {$this->t} (user_id, name, score, stage, gametime, message, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)",
            [$user['id'] ?? null, $name, $r['score'], $r['stage'], $r['gametime'], $msg, now()]
        );
        unset($_SESSION[$this->key]);
        flash('success', 'ランキングに記録しました。');
        $variant = $this->cfg['variants'] ? (string) $r['stage'] : null;
        redirect($this->cfg['path'] . '/ranking', $variant !== null ? ['v' => $variant] : []);
    }

    private function ranking(): never
    {
        $period = query('period') === 'all' ? 'all' : 'month';
        $variant = $this->variant(query('v'));
        $this->page('ranking', [
            'period'  => $period,
            'variant' => $variant,
            'rows'    => $this->top($period, $variant),
        ], $this->cfg['title'] . 'ランキング');
    }

    private function widget(): string
    {
        $this->styles();
        $variant = $this->variant(null);
        return render('games/widget', ['g' => $this, 'variant' => $variant, 'rows' => $this->top('month', $variant, 3)]);
    }

    /** 本体のテンプレート（views/games/）をサイトのレイアウトで表示 */
    private function page(string $tpl, array $vars, string $title): never
    {
        $this->styles();
        $vars['g'] = $this;
        echo render('front/layout', ['title' => $title, 'content' => render('games/' . $tpl, $vars)]);
        exit;
    }

    private function styles(): void
    {
        Plugins::addStyle(asset('css/minigame.css'));
        if (is_file($this->api->dir . '/assets/game.css')) {
            $this->api->addStyle('game.css');
        }
    }

    /* ================= ランキング ================= */

    /** @return list<array<string, mixed>> */
    public function top(string $period, ?string $variant = null, int $limit = self::RANK): array
    {
        [$where, $params] = $this->where($period, $variant);
        return db_all(
            "SELECT s.*, CASE WHEN s.user_id IS NULL THEN s.name
                    ELSE COALESCE(NULLIF(u.display_name, ''), u.username, '（削除されたユーザー）') END AS player,
                    s.user_id IS NULL AS guest
               FROM {$this->t} s LEFT JOIN users u ON u.id = s.user_id
              WHERE $where ORDER BY " . $this->orderBy() . ' LIMIT ' . max(1, $limit),
            $params
        );
    }

    /** この結果を今記録したときの順位（同じ成績なら先に記録した人が上） */
    private function rankOf(array $r, string $period): int
    {
        [$where, $params] = $this->where($period, $this->cfg['variants'] ? (string) $r['stage'] : null);
        if ($this->cfg['lowerIsBetter']) {
            $better = '(s.score < ? OR (s.score = ? AND s.gametime <= ?))';
            $params = [...$params, $r['score'], $r['score'], $r['gametime']];
        } else {
            $better = 's.score >= ?';
            $params = [...$params, $r['score']];
        }
        return 1 + (int) db_val("SELECT COUNT(*) FROM {$this->t} s WHERE $where AND $better", $params);
    }

    private function rankable(array $r): bool
    {
        return $r['score'] > 0;
    }

    private function orderBy(): string
    {
        return $this->cfg['lowerIsBetter'] ? 's.score ASC, s.gametime ASC, s.id ASC' : 's.score DESC, s.id ASC';
    }

    private function where(string $period, ?string $variant): array
    {
        $w = ['1 = 1'];
        $p = [];
        if ($period === 'month') {
            $w[] = 's.created_at >= ?';
            $p[] = date('Y-m-01 00:00:00');
        }
        if ($variant !== null) {
            $w[] = 's.stage = ?';
            $p[] = (int) $variant;
        }
        return [implode(' AND ', $w), $p];
    }

    /** 種類の指定を正しい値に（種類がないゲームは null） */
    private function variant(?string $v): ?string
    {
        $variants = $this->cfg['variants'];
        if (!$variants) {
            return null;
        }
        return isset($variants[(string) $v]) ? (string) $v : (string) ($this->cfg['defaultVariant'] ?? array_key_first($variants));
    }

    /* ================= 表示用 ================= */

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

    public function variantLabel(?string $v): string
    {
        return $v === null ? '' : (string) ($this->cfg['variants'][$v] ?? $v);
    }
}
