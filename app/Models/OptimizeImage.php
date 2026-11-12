<?php

namespace WPSocialReviews\App\Models;

class OptimizeImage extends Model
{
    protected $table = 'wpsr_optimize_images';

    public function getUserIds()
    {
        return static::select('*')->groupBy('user_name')->pluck('user_name')->toArray();
    }
    
    //return ids of successfully resized images
    public function getMediaIds($ids, $userNames, $platforms)
    {
        // Rows are keyed by platform, so an unscoped read reports another
        // platform's resized copy as this one's and yields a missing file.
        $platforms = array_filter((array) $platforms, function ($platform) {
            return $platform !== '' && $platform !== null;
        });
        if (empty($platforms)) {
            return [];
        }

        return static::whereIn('platform', $platforms)->whereIn('user_name', $userNames)->whereIn('media_id', $ids)->where('images_resized', 1)->pluck('media_id')->toArray();
    }
    
    public function deleteMediaByUserName($userName, $platform)
    {
        // Callers clear one platform's upload directory, so only its rows may go.
        static::where('platform', $platform)->where('user_name', $userName)->delete();
    }

    public function deleteMediaByPlatform($platform)
    {
        static::where('platform', $platform)->delete();
    }

    public function getOldPosts($limit, $platform)
    {
        return static::orderBy('last_requested')->where('platform', $platform)->limit($limit)->get();
    }

    public function updateLastRequestedTime($ids, $platforms)
    {
        // A media id alone spans platforms, and touching a foreign row keeps it
        // young enough that deleteLeastUsedImages evicts the wrong images.
        $platforms = array_filter((array) $platforms, function ($platform) {
            return $platform !== '' && $platform !== null;
        });
        if (empty($platforms)) {
            return;
        }

        $dateFormat = gmdate('Y-m-d H:i:s');
        $data = [
            'last_requested'    => $dateFormat,
            'updated_at'        => $dateFormat,
        ];
        static::whereIn('platform', $platforms)->whereIn('media_id', $ids)->update($data);
    }

    public function deleteMedia($mediaId, $userName, $platform)
    {
        // Only the platform whose files were removed may lose its row.
        static::where('platform', $platform)->where('media_id', $mediaId)->where('user_name', $userName)->delete();
    }

    public function updateData($mediaId, $userName, $data)
    {
        // Identity without the platform let an image carrying the same id and user name
        // on another platform claim this row instead of getting one of its own.
        $identity = ['media_id' => $mediaId, 'user_name' => $userName];

        if (isset($data['platform'])) {
            $identity['platform'] = $data['platform'];
        }

        return static::updateOrCreate($identity, $data);
    }
}
