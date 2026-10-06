<?php
namespace App\Controllers;

use App\Models\UserFavorite;

final class FavoritesController extends BaseController
{
    public function add(): never { $this->change(true); }
    public function remove(): never { $this->change(false); }

    private function change(bool $add): never
    {
        if (!UserFavorite::available()) $this->failure('account_feature_unavailable', 503);
        $userId = (int)get_user()['id'];
        $id = (int)request()->post('entity_id', 0);
        $type = (string)request()->post('entity_type', 'post');
        $favorites = new UserFavorite();
        try {
            if ($add && !$favorites->add($userId, $type, $id)) $this->failure('account_favorite_unavailable', 404);
            if (!$add) $favorites->remove($userId, $type, $id);
        } catch (\InvalidArgumentException) { $this->failure('account_favorite_invalid', 422); }
        $message = return_translation($add ? 'account_favorite_added' : 'account_favorite_removed');
        if (request()->isAjax()) response()->json(['status' => 'success', 'saved' => $add,
            'label' => return_translation($add ? 'account_favorite_saved' : 'account_favorite_add'), 'message' => $message]);
        session()->setFlash('success', $message);
        $this->redirect($id);
    }

    private function failure(string $key, int $status): never
    {
        if (request()->isAjax()) response()->json(['status' => 'error', 'message' => return_translation($key)], $status);
        session()->setFlash('error', return_translation($key));
        $this->redirect((int)request()->post('entity_id', 0));
    }

    private function redirect(int $id): never
    {
        // Only a fixed internal destination or a database slug; no untrusted return URL.
        if (request()->post('return_to') === 'post') {
            $post = db()->query('SELECT slug FROM posts WHERE id = ? AND is_published = 1 LIMIT 1', [$id])->getOne();
            if ($post) response()->redirect(base_href('/posts/' . $post['slug']));
        }
        response()->redirect(base_href('/profile/favorites'));
    }
}
