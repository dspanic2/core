<?php

namespace AppBundle\Helpers;

class FileHelper
{
    static function formatSizeUnits($bytes)
    {
        if ($bytes >= 1073741824) {
            $bytes = number_format($bytes / 1073741824, 2).' GB';
        } elseif ($bytes >= 1048576) {
            $bytes = number_format($bytes / 1048576, 2).' MB';
        } elseif ($bytes >= 1024) {
            $bytes = number_format($bytes / 1024, 2).' KB';
        } elseif ($bytes > 1) {
            $bytes = $bytes.' bytes';
        } elseif ($bytes == 1) {
            $bytes = $bytes.' byte';
        } else {
            $bytes = '0 bytes';
        }

        return $bytes;
    }

    static function addHashToFilename($filename)
    {
        $tmp = explode(".", $filename);
        $tmp = end($tmp);
        $file_ext = $tmp;
        $file_name = str_replace(('.'.$file_ext), "", $filename);


        $newfilename = $file_name.'_'.substr(md5(openssl_random_pseudo_bytes(20)), 16).'.'.$file_ext;

        return $newfilename;
    }

    static function generateFilenameFromString($string)
    {
        // sanitize filename
        $filename = preg_replace(
            '~
        [<>:"/\\|?*]|            # file system reserved https://en.wikipedia.org/wiki/Filename#Reserved_characters_and_words
        [\x00-\x1F]|             # control characters http://msdn.microsoft.com/en-us/library/windows/desktop/aa365247%28v=vs.85%29.aspx
        [\x7F\xA0\xAD]|          # non-printing characters DEL, NO-BREAK SPACE, SOFT HYPHEN
        [#\[\]@!$&\'()+,;=]|     # URI reserved https://tools.ietf.org/html/rfc3986#section-2.2
        [{}^\~`]                 # URL unsafe characters https://www.ietf.org/rfc/rfc1738.txt
        ~x',
            '-',
            $string
        );
        // avoids ".", ".." or ".hiddenFiles"
        $filename = ltrim($filename, '.-');
        // optional beautification
        $filename = self::beautifyFilename($filename);
        // maximise filename length to 255 bytes http://serverfault.com/a/9548/44086
        $ext = pathinfo($filename, PATHINFO_EXTENSION);
        $filename = mb_strcut(pathinfo($filename, PATHINFO_FILENAME), 0, 255 - ($ext ? strlen($ext) + 1 : 0), mb_detect_encoding($filename)).($ext ? '.'.$ext : '');

        $search = array("ć", "č", "ž", "š", "đ");
        $replacement = array("c", "c", "z", "s", "dj");
        $filename = str_replace($search, $replacement, $filename);

        return $filename;
    }
    private function beautifyFilename($filename)
    {
        // reduce consecutive characters
        $filename = preg_replace(array(
            // "file   name.zip" becomes "file-name.zip"
            '/ +/',
            // "file___name.zip" becomes "file-name.zip"
            '/_+/',
            // "file---name.zip" becomes "file-name.zip"
            '/-+/'
        ), '-', $filename);
        $filename = preg_replace(array(
            // "file--.--.-.--name.zip" becomes "file.name.zip"
            '/-*\.-*/',
            // "file...name..zip" becomes "file.name.zip"
            '/\.{2,}/'
        ), '.', $filename);
        // lowercase for windows/unix interoperability http://support.microsoft.com/kb/100625
        $filename = mb_strtolower($filename, mb_detect_encoding($filename));
        // ".file-name.-" becomes "file-name"
        $filename = trim($filename, '.-');
        return $filename;
    }

    /**
     * @param $remoteUrl
     * @return bool
     */
    static function checkIfRemoteFileExists($remoteUrl){

        $ch = curl_init($remoteUrl);

        curl_setopt($ch, CURLOPT_NOBODY, true);
        curl_exec($ch);
        $retcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        // $retcode >= 400 -> not found, $retcode = 200, found.
        curl_close($ch);

        if($retcode == "200"){
            return true;
        }
        return false;
    }

    /**
     * Save raw data to a file on disk
     * @param $localPath
     * @param $rawData
     * @return false|int
     */
    static function saveRawDataToFile($rawData, $localPath)
    {
        $bytesWritten = 0;
        if (file_exists($localPath)) {
            unlink($localPath);
        }
        $fp = fopen($localPath, 'x');
        if ($fp) {
            $bytesWritten = fwrite($fp, $rawData);
            fclose($fp);
        }
        return $bytesWritten;
    }
}
