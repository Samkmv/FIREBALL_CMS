<?php
declare(strict_types=1);
namespace Fireball\ReelPlayer\Services;

use App\Services\UploadPolicy;
use Fireball\ReelPlayer\Repositories\Chapters;

/** Parse text metadata only; no FILE path is ever opened or downloaded. */
final class CueParser
{
    public const MAX_BYTES = 1048576;

    public static function upload(array $file, string $filename, float $duration = 0): array
    {
        UploadPolicy::assertUpload((int)($file['error'] ?? UPLOAD_ERR_NO_FILE),(int)($file['size'] ?? 0),self::MAX_BYTES);
        if (strtolower(pathinfo((string)($file['name'] ?? ''),PATHINFO_EXTENSION))!=='cue') throw new \InvalidArgumentException('Выберите текстовый файл .cue.');
        $path = (string)($file['tmp_name'] ?? '');
        if (!is_uploaded_file($path)) throw new \InvalidArgumentException('Не удалось прочитать CUE-файл.');
        $bytes = file_get_contents($path,false,null,0,self::MAX_BYTES+1);
        if ($bytes===false) throw new \InvalidArgumentException('Не удалось прочитать CUE-файл.');
        return self::parse($bytes,$filename,$duration);
    }

    private static function decode(string $bytes): array
    {
        if ($bytes==='' || strlen($bytes)>self::MAX_BYTES) throw new \InvalidArgumentException('CUE-файл должен быть непустым и не больше 1 МБ.');
        $encoding = 'UTF-8';
        if (str_starts_with($bytes,"\xef\xbb\xbf")) $bytes = substr($bytes,3);
        elseif (str_starts_with($bytes,"\xff\xfe")) { $encoding='UTF-16LE';$bytes=substr($bytes,2); }
        elseif (str_starts_with($bytes,"\xfe\xff")) { $encoding='UTF-16BE';$bytes=substr($bytes,2); }
        elseif (!mb_check_encoding($bytes,'UTF-8')) $encoding='Windows-1251';
        if (!mb_check_encoding($bytes,$encoding)) throw new \InvalidArgumentException('Повреждённая кодировка CUE. Сохраните файл в UTF-8.');
        $text = $encoding==='UTF-8' ? $bytes : @iconv($encoding,'UTF-8',$bytes);
        if ($text===false || str_contains($text,"\0")) throw new \InvalidArgumentException('CUE должен быть текстовым файлом. Сохраните его в UTF-8.');
        return [$text,$encoding];
    }

    private static function value(string $value, int $line): string
    {
        $value = trim($value);
        if (str_starts_with($value,'"')) {
            if (strlen($value)<2 || !str_ends_with($value,'"')) throw new \InvalidArgumentException("Незакрытая кавычка в строке {$line} CUE.");
            $value = substr($value,1,-1);
        }
        return trim($value);
    }

    private static function filename(string $name): string
    {
        $name = basename(str_replace('\\','/',trim($name)));
        if (class_exists(\Normalizer::class)) $name = \Normalizer::normalize($name,\Normalizer::FORM_C) ?: $name;
        return mb_strtolower($name,'UTF-8');
    }

    public static function parse(string $bytes, string $filename, float $duration = 0): array
    {
        [$text,$encoding] = self::decode($bytes);
        $files = []; $chapters = []; $current = null; $globalArtist = ''; $numbers = []; $warnings = []; $titles = [];
        $ignoredIndexes = false;
        foreach (preg_split('/\r\n|\n|\r/',$text) as $index=>$raw) {
            $line = trim($raw); $lineNumber = $index+1;
            if ($line==='' || preg_match('/^REM(?:\s|$)/i',$line)) continue;
            if (preg_match('/^FILE\s+(?:"([^"]+)"|(.+?))\s+(\S+)$/i',$line,$match)) {
                $files[] = $match[1]!=='' ? $match[1] : trim($match[2]);
                if (count($files)>1) throw new \InvalidArgumentException('Этот CUE содержит несколько аудиофайлов. Выберите CUE для одного непрерывного трека.');
                if (self::filename($files[0])!==self::filename($filename)) throw new \InvalidArgumentException('FILE в CUE не совпадает с именем выбранного аудиофайла.');
                continue;
            }
            if (preg_match('/^TRACK\s+(\d+)\s+(\S+)$/i',$line,$match)) {
                if (!$files || strtoupper($match[2])!=='AUDIO') throw new \InvalidArgumentException('В CUE поддерживаются только AUDIO-дорожки после FILE.');
                $number = (int)$match[1];
                if ($number<1 || isset($numbers[$number])) throw new \InvalidArgumentException('Повторяющийся или некорректный номер TRACK в CUE.');
                if (count($chapters)>=Chapters::MAX_ENTRIES) throw new \InvalidArgumentException('В CUE больше 1000 глав.');
                $numbers[$number] = true;
                $chapters[] = ['title'=>'Глава '.str_pad((string)$number,2,'0',STR_PAD_LEFT),'artist'=>$globalArtist,'start'=>null];
                $current = count($chapters)-1;
                $titles[$current] = false;
                continue;
            }
            if (preg_match('/^(TITLE|PERFORMER)\s+(.+)$/i',$line,$match)) {
                $value = self::value($match[2],$lineNumber);
                if (strtoupper($match[1])==='PERFORMER') {
                    if ($current===null) $globalArtist=$value; else $chapters[$current]['artist']=$value;
                } elseif ($current!==null && $value!=='') { $chapters[$current]['title']=$value; $titles[$current]=true; }
                continue;
            }
            if (preg_match('/^INDEX\s+(\d+)\s+(\d+):(\d{2}):(\d{2})$/i',$line,$match)) {
                if ($current===null) throw new \InvalidArgumentException("INDEX без TRACK в строке {$lineNumber} CUE.");
                $seconds = (int)$match[3]; $frames = (int)$match[4];
                if ($seconds>=60 || $frames>=75) throw new \InvalidArgumentException("Неверное время INDEX в строке {$lineNumber}: секунды 00–59, кадры 00–74.");
                if ((int)$match[1]!==1) { $ignoredIndexes=true;continue; }
                if ($chapters[$current]['start']!==null) throw new \InvalidArgumentException('В одной TRACK найдено несколько INDEX 01.');
                $chapters[$current]['start']=(int)$match[2]*60+$seconds+$frames/75;
                continue;
            }
            if (preg_match('/^(FILE|TRACK|INDEX|TITLE|PERFORMER)(?:\s|$)/i',$line)) throw new \InvalidArgumentException("Некорректная строка {$lineNumber} CUE: {$line}.");
            // Other standard metadata (PREGAP, FLAGS, CATALOG...) does not
            // change INDEX 01's absolute position in the selected audio file.
        }
        if (count($files)!==1 || !$chapters) throw new \InvalidArgumentException('В CUE нужны FILE и хотя бы одна AUDIO-дорожка с INDEX 01.');
        foreach ($chapters as $chapter) if ($chapter['start']===null) throw new \InvalidArgumentException('У каждой TRACK должен быть INDEX 01.');
        $entries = Chapters::validateEntries($chapters,$duration);
        if ($duration<=0) $warnings[]='Длительность выбранной записи пока неизвестна. Проверьте границы глав после начала воспроизведения.';
        if (in_array(false,$titles,true)) $warnings[]='Для дорожек без TITLE использованы временные названия «Глава XX». Замените их названиями композиций перед сохранением.';
        if ($ignoredIndexes) $warnings[]='Использованы только INDEX 01; INDEX 00 и дополнительные индексы не добавлены как главы.';
        if ($encoding!=='UTF-8') $warnings[]="Применена кодировка {$encoding}; названия преобразованы в UTF-8. Проверьте правильность текста.";
        if (array_column($entries,'start')!==array_column($chapters,'start')) {
            $original = array_map(static fn(array $chapter): float => round($chapter['start'],6),$chapters);
            if (array_column($entries,'start')!==$original) $warnings[]='Главы упорядочены по времени начала.';
        }
        return ['entries'=>$entries,'warnings'=>$warnings,'encoding'=>$encoding,'file'=>basename(str_replace('\\','/',$files[0]))];
    }
}
