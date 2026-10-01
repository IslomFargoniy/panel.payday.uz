<?php

namespace App\Services\Worker;

use Illuminate\Http\UploadedFile;

class AvatarService
{
    /**
     * Resize and optimize avatar image to safe dimensions and size for Hikvision terminals.
     * Returns relative path within storage/app/public, e.g. "avatars/avatar_xyz.jpg".
     */
    public function saveOptimizedAvatar(UploadedFile $file): string
    {
        $filename = 'avatars/' . uniqid('avatar_', true) . '.jpg';
        $destinationPath = storage_path('app/public/' . $filename);

        $avatarsDir = storage_path('app/public/avatars');
        if (!file_exists($avatarsDir)) {
            @mkdir($avatarsDir, 0775, true);
        }

        if (extension_loaded('gd')) {
            $imageInfo = @getimagesize($file->getRealPath());
            if ($imageInfo) {
                $mime = $imageInfo['mime'];
                $src = null;

                if ($mime === 'image/jpeg' || $mime === 'image/jpg') {
                    $src = @imagecreatefromjpeg($file->getRealPath());
                    if ($src && function_exists('exif_read_data')) {
                        $exif = @exif_read_data($file->getRealPath());
                        if (!empty($exif['Orientation'])) {
                            switch ($exif['Orientation']) {
                                case 8:
                                    $src = imagerotate($src, 90, 0);
                                    break;
                                case 3:
                                    $src = imagerotate($src, 180, 0);
                                    break;
                                case 6:
                                    $src = imagerotate($src, -90, 0);
                                    break;
                            }
                        }
                    }
                } elseif ($mime === 'image/png') {
                    $src = @imagecreatefrompng($file->getRealPath());
                } elseif ($mime === 'image/webp') {
                    $src = @imagecreatefromwebp($file->getRealPath());
                }

                if ($src) {
                    $width = imagesx($src);
                    $height = imagesy($src);

                    // Max dimensions 800px for optimal face recognition and small payload size
                    $maxDim = 800;
                    if ($width > $maxDim || $height > $maxDim) {
                        if ($width > $height) {
                            $newWidth = $maxDim;
                            $newHeight = (int) ($height * ($maxDim / $width));
                        } else {
                            $newHeight = $maxDim;
                            $newWidth = (int) ($width * ($maxDim / $height));
                        }
                    } else {
                        $newWidth = $width;
                        $newHeight = $height;
                    }

                    $dst = imagecreatetruecolor($newWidth, $newHeight);
                    $white = imagecolorallocate($dst, 255, 255, 255);
                    imagefilledrectangle($dst, 0, 0, $newWidth, $newHeight, $white);
                    imagecopyresampled($dst, $src, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

                    imagejpeg($dst, $destinationPath, 85);
                    imagedestroy($src);
                    imagedestroy($dst);

                    return $filename;
                }
            }
        }

        return $file->store('avatars', 'public');
    }
}
