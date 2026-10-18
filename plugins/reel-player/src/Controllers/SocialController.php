<?php
declare(strict_types=1);
namespace Fireball\ReelPlayer\Controllers;
use Fireball\ReelPlayer\Repositories\Social;

final class SocialController
{
    private function repository():Social{return new Social((int)(get_user()['id']??0));}
    private function query(string $key,string $default):string
    {
        $value=request()->get($key,$default);
        if(!is_string($value)&&!is_int($value))throw new \InvalidArgumentException('Некорректный параметр запроса.');
        return (string)$value;
    }
    private function page(callable $operation):string
    {
        if(theme()->getActiveTheme()['slug']!=='tape-room')abort('Страница недоступна.',404);
        header('Cache-Control: private, no-store');
        try{return $operation();}
        catch(\OutOfBoundsException $error){abort($error->getMessage(),404);}
        catch(\InvalidArgumentException $error){abort($error->getMessage(),400);}
        catch(\RuntimeException $error){if($error->getCode()===503)abort($error->getMessage(),503);throw $error;}
    }
    public function index():string
    {
        return $this->page(function():string{
            $list=$this->query('list','all');if($list==='following'&&!check_auth())response()->redirect(base_href('/login'));
            $people=$this->repository()->directory($this->query('q',''),max(1,(int)$this->query('page','1')),$list);
            return theme()->render('people',['title'=>'Пользователи','tr_wide_page'=>true,'people'=>$people]);
        });
    }
    public function profile():string
    {
        return $this->page(function():string{
            $repo=$this->repository();$person=$repo->profile((int)get_route_param('id'));$tab=$this->query('tab','published');$page=max(1,(int)$this->query('page','1'));
            if(!in_array($tab,['published','collection','following','followers'],true))throw new \InvalidArgumentException('Неизвестный раздел профиля.');
            $content=in_array($tab,['following','followers'],true)?$repo->directory('', $page,$tab,$person['id']):$repo->albums($person['id'],$tab,$page);
            return theme()->render('person',['title'=>$person['name'].' — профиль','tr_wide_page'=>true,'person'=>$person,'tab'=>$tab,'social_content'=>$content]);
        });
    }
    private function mutate(callable $operation,string $redirect):void
    {
        header('Cache-Control: private, no-store');
        try{$result=$operation();if(request()->isAjax())response()->json(['status'=>true,...$result]);session()->setFlash('success','Изменения сохранены.');}
        catch(\InvalidArgumentException|\OutOfBoundsException $error){if(request()->isAjax())response()->json(['status'=>false,'message'=>$error->getMessage()],$error instanceof \OutOfBoundsException?404:422);session()->setFlash('error',$error->getMessage());}
        catch(\RuntimeException $error){if($error->getCode()!==503)throw $error;if(request()->isAjax())response()->json(['status'=>false,'message'=>$error->getMessage()],503);session()->setFlash('error',$error->getMessage());}
        response()->redirect($redirect);
    }
    public function follow():void
    {
        $id=(int)get_route_param('id');$this->mutate(function()use($id):array{
            $value=request()->getData()['following']??null;if(!in_array($value,['0','1',0,1],true))throw new \InvalidArgumentException('Некорректное действие подписки.');
            return $this->repository()->follow($id,(bool)$value);
        },base_href('/tape-room/people/'.$id));
    }
    public function saveSettings():void{$this->mutate(fn():array=>['settings'=>$this->repository()->saveSettings(request()->getData())],base_href('/profile/settings'));}
}
