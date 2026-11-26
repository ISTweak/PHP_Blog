<?php
declare(strict_types=1);

/**
 * プラグインの有効化・無効化（管理者のみ）
 */
final class AdminPluginController
{
    public function index(): void
    {
        require_role('admin');
        view('admin/plugins', [
            'title'   => 'プラグイン',
            'plugins' => Plugins::discover(),
        ], 'admin/layout');
    }

    public function enable(string $id): void
    {
        require_role('admin');
        $error = Plugins::enable($id);
        if ($error !== null) {
            flash('error', $error);
        } else {
            $name = Plugins::discover()[$id]['name'] ?? $id;
            flash('success', '「' . $name . '」を有効にしました。');
        }
        redirect('/admin/plugins');
    }

    public function disable(string $id): void
    {
        require_role('admin');
        Plugins::disable($id);
        $name = Plugins::discover()[$id]['name'] ?? $id;
        flash('success', '「' . $name . '」を無効にしました（保存されたデータは残っています）。');
        redirect('/admin/plugins');
    }
}
