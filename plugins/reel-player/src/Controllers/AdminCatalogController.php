<?php
declare(strict_types=1);
namespace Fireball\ReelPlayer\Controllers;

use Fireball\ReelPlayer\Repositories\AdminCatalog;
use Fireball\ReelPlayer\Repositories\Genres;
use Fireball\ReelPlayer\Services\GoogleDrive;

final class AdminCatalogController
{
    public function settings(): string
    {
        header('Cache-Control: private, no-store');
        return plugin_view('reel-player','settings',[
            'title'=>'Tape Room · Настройки',
            'drive'=>(new GoogleDrive((int)get_user()['id'],null,check_creator()))->status(),
        ]);
    }

    public function saveSettings(): void
    {
        if (!check_creator()) abort('Настройки подключения доступны только создателю сайта.',403);
        try {
            (new GoogleDrive((int)get_user()['id'],null,true))->settings((string)request()->post('client_id',''),(string)request()->post('client_secret',''));
            session()->setFlash('success','Настройки Google Drive сохранены.');
        } catch (\InvalidArgumentException $error) {
            session()->setFlash('error',$error->getMessage());
        }
        response()->redirect(base_href('/admin/reel-player/settings'));
    }

    public function index(): string
    {
        header('Cache-Control: private, no-store');
        $owner=max(0,(int)request()->get('owner',0)); $genre=max(0,(int)request()->get('genre',0));
        return plugin_view('reel-player','overview',[
            'title'=>'Tape Room · Обзор','theme_active'=>theme()->getActiveTheme()['slug']==='tape-room',
            'owner_filter'=>$owner,'genre_filter'=>$genre,'genres'=>(new Genres())->all(),
            ...(new AdminCatalog())->overview($owner,$genre,(int)request()->get('page',1)),
        ]);
    }

    public function genres(): string
    {
        header('Cache-Control: private, no-store');
        $id=max(0,(int)request()->get('id',0));
        try { $genre=$id ? (new Genres())->find($id) : null; }
        catch (\InvalidArgumentException $error) { abort($error->getMessage(),404); }
        $overview=$id ? (new AdminCatalog())->overview(0,$id,(int)request()->get('page',1)) : ['albums'=>[],'page'=>1,'total'=>0];
        return plugin_view('reel-player','genres',[
            'title'=>'Tape Room · Жанры','genre'=>$genre,'query'=>(string)request()->get('q',''),
            'tracks'=>$id ? (new Genres())->tracks($id) : [],
            'genres'=>(new Genres())->counts((string)request()->get('q','')),
            'albums'=>$overview['albums'],'page'=>$overview['page'],'total'=>$overview['total'],
        ]);
    }

    public function saveGenre(): void
    {
        try {
            $id=(new Genres())->save((int)request()->post('id',0),request()->post('name',''),request()->post('description',''));
            session()->setFlash('success','Жанр сохранён.');
            response()->redirect(base_href('/admin/reel-player/genres?id='.$id));
        } catch (\InvalidArgumentException $error) {
            session()->setFlash('error',$error->getMessage());
            response()->redirect(base_href('/admin/reel-player/genres'));
        }
    }

    public function album(): string
    {
        header('Cache-Control: private, no-store');
        try { $album=(new AdminCatalog())->album((int)get_route_param('id')); }
        catch (\InvalidArgumentException $error) { abort($error->getMessage(),404); }
        return plugin_view('reel-player','moderation',['title'=>'Tape Room · Модерация','album'=>$album,'revision'=>AdminCatalog::revision($album)]);
    }

    public function review(): void
    {
        $id=(int)request()->post('id',0);
        try {
            (new AdminCatalog())->review($id,(string)request()->post('decision',''),(string)request()->post('note',''),(int)get_user()['id'],(string)request()->post('revision',''));
            session()->setFlash('success','Решение по альбому сохранено.');
        } catch (\InvalidArgumentException $error) { session()->setFlash('error',$error->getMessage()); }
        response()->redirect(base_href('/admin/reel-player/albums/'.$id));
    }
}
