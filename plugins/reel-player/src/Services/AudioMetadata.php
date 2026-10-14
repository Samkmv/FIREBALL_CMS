<?php
declare(strict_types=1);
namespace Fireball\ReelPlayer\Services;

/** Reads bounded container headers; never decodes or downloads a whole recording. */
final class AudioMetadata
{
    public const MAX_BYTES = 2097152;

    public static function read(string $head, string $filename, int $size, string $tail = ''): array
    {
        $head=substr($head,0,self::MAX_BYTES); $tail=substr($tail,-self::MAX_BYTES);
        $result=[]; $quality=AudioQuality::describe($head,$filename,'',$size);
        if (!empty($quality['duration'])) $result['duration']=$quality['duration'];
        if (str_starts_with($head,'ID3')) self::id3($head,$result);
        if (str_starts_with($head,'fLaC')) self::flac($head,$result);
        if (substr($head,0,4)==='RIFF' && substr($head,8,4)==='WAVE') self::wave($head,$result);
        if (str_starts_with($head,'OggS')) self::ogg($head,$tail,$result);
        if (strtolower(pathinfo($filename,PATHINFO_EXTENSION))==='m4a') {
            self::atoms($head,$result);
            // A moov atom at the end is common; only use a complete bounded atom.
            for ($position=0,$count=0; $count<64 && ($position=strpos($tail,'moov',$position))!==false; $count++,$position+=4) {
                if ($position<4) continue; $length=self::be32($tail,$position-4);
                if ($length>=8 && $length<=strlen($tail)-$position+4) self::atoms(substr($tail,$position-4,$length),$result);
            }
        }
        if (strtolower(pathinfo($filename,PATHINFO_EXTENSION))==='mp3') self::mp3($head,$result);
        foreach (['title','artist','album','albumArtist','genre'] as $key) if (isset($result[$key])) $result[$key]=self::clean($result[$key],$key==='genre'?100:240);
        if (isset($result['duration']) && (!is_finite((float)$result['duration']) || $result['duration']<=0 || $result['duration']>604800)) unset($result['duration']);
        return $result;
    }

    private static function clean(string $value,int $limit): string { return mb_substr(trim(preg_replace('/[\x00-\x1f\x7f]/u',' ',mb_convert_encoding($value,'UTF-8','UTF-8')) ?? ''),0,$limit); }
    private static function be32(string $bytes,int $offset): int { return strlen($bytes)>=$offset+4 ? (int)unpack('N',substr($bytes,$offset,4))[1] : 0; }
    private static function le32(string $bytes,int $offset): int { return strlen($bytes)>=$offset+4 ? (int)unpack('V',substr($bytes,$offset,4))[1] : 0; }
    private static function sync(string $bytes): int { $value=0; if(strlen($bytes)!==4)return 0;foreach(str_split($bytes) as $byte){if(ord($byte)>127)return 0;$value=($value<<7)|ord($byte);}return $value; }
    private static function text(string $bytes): string
    {
        if($bytes==='')return ''; $encoding=ord($bytes[0]);$bytes=substr($bytes,1);
        return match($encoding){0=>mb_convert_encoding($bytes,'UTF-8','ISO-8859-1'),1=>mb_convert_encoding($bytes,'UTF-8','UTF-16'),2=>mb_convert_encoding($bytes,'UTF-8','UTF-16BE'),3=>$bytes,default=>''};
    }
    private static function terminator(string $bytes,int $offset,bool $wide): int
    {
        if(!$wide){$position=strpos($bytes,"\0",$offset);return $position===false?strlen($bytes):$position+1;}
        for($i=$offset;$i+1<strlen($bytes);$i+=2)if(substr($bytes,$i,2)==="\0\0")return $i+2;
        return strlen($bytes);
    }
    private static function id3(string $bytes,array &$result): void
    {
        if(strlen($bytes)<10)return; $version=ord($bytes[3]);if(!in_array($version,[2,3,4],true))return;
        $tag=substr($bytes,10,min(self::sync(substr($bytes,6,4)),self::MAX_BYTES-10));
        if($version<4 && (ord($bytes[5])&128))$tag=str_replace("\xff\0","\xff",$tag);
        $offset=0;if($version>=3 && (ord($bytes[5])&64))$offset=$version===4?self::sync(substr($tag,0,4)):self::be32($tag,0)+4;
        $mapping=['TIT2'=>'title','TT2'=>'title','TPE1'=>'artist','TP1'=>'artist','TPE2'=>'albumArtist','TP2'=>'albumArtist','TALB'=>'album','TAL'=>'album','TCON'=>'genre','TCO'=>'genre'];
        for($frames=0;$frames<512 && $offset+($version===2?6:10)<=strlen($tag);$frames++){
            $id=substr($tag,$offset,$version===2?3:4);if(!preg_match('/^[A-Z0-9]+$/D',$id))break;
            $length=$version===2 ? self::be32("\0".substr($tag,$offset+3,3),0) : ($version===4?self::sync(substr($tag,$offset+4,4)):self::be32($tag,$offset+4));
            $flags=$version===2?0:ord($tag[$offset+9]);$offset+=$version===2?6:10;
            if($length<=0 || $length>strlen($tag)-$offset)break;$body=substr($tag,$offset,$length);$offset+=$length;
            if(($version===3 && ($flags&192)) || ($version===4 && ($flags&12)))continue;
            if($version===4 && ($flags&2))$body=str_replace("\xff\0","\xff",$body);
            if(isset($mapping[$id]))$result[$mapping[$id]]=self::text($body);
            if(in_array($id,['TLEN','TLE'],true) && is_numeric($value=self::text($body)))$result['duration']=(float)$value/1000;
            if($id==='APIC' && strlen($body)>4){
                $encoding=ord($body[0]);$mimeEnd=strpos($body,"\0",1);if($mimeEnd===false || $mimeEnd+1>=strlen($body))continue;
                $type=ord($body[$mimeEnd+1]);$start=self::terminator($body,$mimeEnd+2,in_array($encoding,[1,2],true));
                if($start<strlen($body) && (!isset($result['cover']) || $type===3))$result['cover']=substr($body,$start);
            }
        }
        if(isset($result['genre']) && preg_match('/^\(?(\d+)\)?$/D',$result['genre'],$match))$result['genre']=[0=>'Blues',1=>'Classic Rock',2=>'Country',4=>'Disco',5=>'Funk',7=>'Hip-Hop',8=>'Jazz',9=>'Metal',12=>'Other',13=>'Pop',15=>'Rap',16=>'Reggae',17=>'Rock',18=>'Techno',24=>'Soundtrack',32=>'Classical'][$match[1]] ?? $result['genre'];
    }
    private static function comments(string $bytes,array &$result): void
    {
        $offset=4+self::le32($bytes,0);if($offset+4>strlen($bytes))return;$count=min(512,self::le32($bytes,$offset));$offset+=4;
        $mapping=['TITLE'=>'title','ARTIST'=>'artist','ALBUM'=>'album','ALBUMARTIST'=>'albumArtist','GENRE'=>'genre'];
        for($i=0;$i<$count && $offset+4<=strlen($bytes);$i++){
            $length=self::le32($bytes,$offset);$offset+=4;if($length>strlen($bytes)-$offset)break;
            $pair=explode('=',substr($bytes,$offset,$length),2);$offset+=$length;if(count($pair)!==2)continue;$name=strtoupper($pair[0]);
            if(isset($mapping[$name]))$result[$mapping[$name]]=$pair[1];
            if($name==='METADATA_BLOCK_PICTURE' && strlen($pair[1])<=self::MAX_BYTES){$picture=base64_decode($pair[1],true);if($picture!==false)self::picture($picture,$result);}
        }
    }
    private static function picture(string $bytes,array &$result): void
    {
        if(strlen($bytes)<32)return;$type=self::be32($bytes,0);$offset=8+self::be32($bytes,4);
        if($offset+4>strlen($bytes))return;$offset+=4+self::be32($bytes,$offset);$offset+=16;
        if($offset+4>strlen($bytes))return;$length=self::be32($bytes,$offset);$offset+=4;
        if($length>0 && $length<=strlen($bytes)-$offset && (!isset($result['cover']) || $type===3))$result['cover']=substr($bytes,$offset,$length);
    }
    private static function flac(string $bytes,array &$result): void
    {
        for($offset=4,$blocks=0;$blocks<512 && $offset+4<=strlen($bytes);$blocks++){
            $flag=ord($bytes[$offset]);$length=self::be32("\0".substr($bytes,$offset+1,3),0);$offset+=4;
            if($length>strlen($bytes)-$offset)break;$body=substr($bytes,$offset,$length);$offset+=$length;
            if(($flag&127)===4)self::comments($body,$result);if(($flag&127)===6)self::picture($body,$result);if($flag&128)break;
        }
    }
    private static function wave(string $bytes,array &$result): void
    {
        $rate=0;$mapping=['INAM'=>'title','IART'=>'artist','IPRD'=>'album','IGNR'=>'genre'];
        for($offset=12,$chunks=0;$chunks<512 && $offset+8<=strlen($bytes);$chunks++){
            $name=substr($bytes,$offset,4);$length=self::le32($bytes,$offset+4);$offset+=8;
            if($name==='fmt ' && $length>=16 && $offset+16<=strlen($bytes))$rate=self::le32($bytes,$offset+8);
            if($name==='data' && $rate>0)$result['duration']=$length/$rate;
            if($length>strlen($bytes)-$offset)break;
            if($name==='LIST' && substr($bytes,$offset,4)==='INFO')for($cursor=$offset+4;$cursor+8<=$offset+$length;){$tag=substr($bytes,$cursor,4);$size=self::le32($bytes,$cursor+4);$cursor+=8;if($size>$offset+$length-$cursor)break;if(isset($mapping[$tag]))$result[$mapping[$tag]]=substr($bytes,$cursor,$size);$cursor+=$size+($size&1);}
            $offset+=$length+($length&1);
        }
    }
    private static function ogg(string $head,string $tail,array &$result): void
    {
        $packets=[];$packet='';$serial=substr($head,14,4);
        for($offset=0,$pages=0;$pages<256 && $offset+27<=strlen($head) && substr($head,$offset,4)==='OggS';$pages++){
            $segments=ord($head[$offset+26]);if($offset+27+$segments>strlen($head))break;$cursor=$offset+27+$segments;
            for($i=0;$i<$segments;$i++){$length=ord($head[$offset+27+$i]);if($cursor+$length>strlen($head))break 2;$packet.=substr($head,$cursor,$length);$cursor+=$length;if($length<255){$packets[]=$packet;$packet='';}}
            $offset=$cursor;if(count($packets)>=3)break;
        }
        $first=$packets[0]??'';$rate=0;$skip=0;
        if(str_starts_with($first,'OpusHead') && strlen($first)>=12){$rate=48000;$skip=unpack('v',substr($first,10,2))[1];}
        if(substr($first,0,7)==="\x01vorbis" && strlen($first)>=16)$rate=self::le32($first,12);
        foreach($packets as $packet){if(str_starts_with($packet,'OpusTags'))self::comments(substr($packet,8),$result);elseif(substr($packet,0,7)==="\x03vorbis")self::comments(substr($packet,7),$result);}
        if($rate>0){$bytes=$tail?:$head;$last=0;for($position=0,$pages=0;$pages<1024 && ($position=strpos($bytes,'OggS',$position))!==false;$pages++,$position+=4){if($position+27>strlen($bytes) || substr($bytes,$position+14,4)!==$serial)continue;$parts=unpack('Vlow/Vhigh',substr($bytes,$position+6,8));if($parts['high']<1024)$last=max($last,$parts['high']*4294967296+$parts['low']);}if($last>$skip)$result['duration']=($last-$skip)/$rate;}
    }
    private static function atoms(string $bytes,array &$result,int $depth=0): void
    {
        if($depth>8)return;
        for($offset=0,$atoms=0;$atoms<512 && $offset+8<=strlen($bytes);$atoms++){
            $size=self::be32($bytes,$offset);$name=substr($bytes,$offset+4,4);if($size===0)$size=strlen($bytes)-$offset;if($size<8 || $size>strlen($bytes)-$offset)break;
            $body=substr($bytes,$offset+8,$size-8);$offset+=$size;
            if(in_array($name,['moov','udta','ilst','trak','mdia'],true))self::atoms($body,$result,$depth+1);
            elseif($name==='meta')self::atoms(substr($body,4),$result,$depth+1);
            elseif($name==='mvhd' && strlen($body)>=20){$version=ord($body[0]);$scale=self::be32($body,$version===1?20:12);$duration=$version===1 ? self::be32($body,24)*4294967296+self::be32($body,28) : self::be32($body,16);if($scale>0)$result['duration']=$duration/$scale;}
            elseif(isset((["\xa9nam"=>'title',"\xa9ART"=>'artist','aART'=>'albumArtist',"\xa9alb"=>'album',"\xa9gen"=>'genre'])[$name])){
                $mapping=["\xa9nam"=>'title',"\xa9ART"=>'artist','aART'=>'albumArtist',"\xa9alb"=>'album',"\xa9gen"=>'genre'];if(substr($body,4,4)==='data' && strlen($body)>=16)$result[$mapping[$name]]=substr($body,16);
            } elseif($name==='covr' && substr($body,4,4)==='data' && strlen($body)>16)$result['cover']=substr($body,16);
        }
    }
    private static function mp3(string $bytes,array &$result): void
    {
        if(!empty($result['duration']))return;$offset=str_starts_with($bytes,'ID3')?10+self::sync(substr($bytes,6,4)):0;
        for($scanned=0;$scanned<65536 && $offset+4<strlen($bytes);$scanned++,$offset++){
            $frame=AudioQuality::mpegFrame(substr($bytes,$offset,4));if(!$frame || $frame['layer']!==3 || $offset+$frame['length']>strlen($bytes))continue;
            $side=$frame['version']===3 ? ($frame['channels']===1?17:32) : ($frame['channels']===1?9:17);
            $xing=$offset+4+((ord($bytes[$offset+1])&1)?0:2)+$side;
            if(in_array(substr($bytes,$xing,4),['Xing','Info'],true) && (self::be32($bytes,$xing+4)&1)){$frames=self::be32($bytes,$xing+8);if($frames>0)$result['duration']=$frames*($frame['version']===3?1152:576)/$frame['rate'];}
            $vbri=$offset+36;if(substr($bytes,$vbri,4)==='VBRI')$result['duration']=self::be32($bytes,$vbri+14)*($frame['version']===3?1152:576)/$frame['rate'];
            break;
        }
    }
}
