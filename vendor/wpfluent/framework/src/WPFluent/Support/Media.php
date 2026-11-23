<?php

namespace WPSocialReviews\Framework\Support;

use RuntimeException;
use InvalidArgumentException;
use WPSocialReviews\Framework\Http\Request\File;

class Media
{
    /**
     * Whether this object's uploads skip reading EXIF.
     *
     * @var bool
     */
    protected $withoutExif = false;

    /**
     * Whether this object's uploads skip creating image sizes.
     *
     * @var bool
     */
    protected $withoutSizes = false;

    /**
     * Turn off reading exif for this object's uploads.
     * 
     * @return $this
     */
    public function withoutExif()
    {
        $this->withoutExif = true;
        return $this;
    }

    /**
     * Turn off creating image sizes for this object's uploads.
     * 
     * @return $this
     */
    public function withoutSizes()
    {
        $this->withoutSizes = true;
        return $this;
    }

    /**
     * Turn off reading exif and creating image sizes.
     * 
     * @return $this
     */
    public function withoutExifAndSizes()
    {
        $this->withoutExif();
        $this->withoutSizes();
        return $this;
    }

    /**
     * Sideload an uploaded file (the request's File, or a $_FILES array), a
     * remote file URL or a base64 image.
     *
     * @param \WPSocialReviews\Framework\Http\Request\File|array|string $resource
     * @param int|null $postId        Optional post ID to attach the media to
     * @param string|null $filename   Optional file name for remote URL
     *
     * @return array
     * @throws InvalidArgumentException
     * @throws RuntimeException
     */
    public function upload($resource, $postId = 0, $filename = null)
    {
        [$data, $tmp] = $this->prepareMedia($resource, $filename);

        // On for this upload only; a filter someone else added stays.
        $filters = array_keys(array_filter([
            'wp_read_image_metadata'            => $this->withoutExif,
            'intermediate_image_sizes_advanced' => $this->withoutSizes,
        ], function ($on, $filter) {
            return $on && !has_filter($filter, '__return_empty_array');
        }, ARRAY_FILTER_USE_BOTH));

        foreach ($filters as $filter) {
            add_filter($filter, '__return_empty_array');
        }

        try {
            $attachmentId = media_handle_sideload($data, $postId);
        } finally {
            foreach ($filters as $filter) {
                remove_filter($filter, '__return_empty_array');
            }
        }

        $this->cleanup($tmp, $attachmentId);

        return [
            'success' => true,
            'id'      => $attachmentId,
            'url'     => wp_get_attachment_url($attachmentId),
        ];
    }

    /**
     * Prepare the file array to use with media_handle_sideload.
     * 
     * @param \WPSocialReviews\Framework\Http\Request\File|array|string $resource
     * @param string|null $filename   Optional file name for remote URL
     *
     * @return array
     * @throws InvalidArgumentException
     * @throws RuntimeException
     */
    protected function prepareMedia($resource, $filename = null)
    {
        $this->includeMediaFunctions();

        // The request's uploaded file ($request->file('image')).
        if ($resource instanceof File) {
            $resource = $resource->toArray();
        }

        if (is_array($resource)) {
            if (empty($resource['tmp_name'])) {
                throw new InvalidArgumentException(
                    'Invalid file array: tmp_name is required.'
                );
            }

            return [[
                'name'     => $resource['name'] ?? 'file',
                'tmp_name' => $resource['tmp_name'],
                'type'     => $resource['type'] ?? null,
                'error'    => $resource['error'] ?? 0,
                'size'     => $resource['size_in_bytes'] ?? $resource['size'] ?? null,
            ], null];
        }

        if ($this->isBase64($resource)) {
            return $this->prepareBase64($resource, $filename);
        }

        if (is_string($resource) && filter_var($resource, FILTER_VALIDATE_URL)) {
            $tmp = download_url($resource);

            if (is_wp_error($tmp)) {
                throw new RuntimeException(
                    'Failed to download remote file: ' . $tmp->get_error_message()
                );
            }

            return [[
                'tmp_name' => $tmp,
                'name'     => $filename ?: basename(
                    parse_url($resource, PHP_URL_PATH)
                ),
            ], $tmp];
        }

        throw new InvalidArgumentException('Invalid file array or URL provided.');
    }

    /**
     * Write a base64 image to a temporary file for media_handle_sideload.
     *
     * @param  string $imageData
     * @param  string|null $filename
     * @return array
     * @throws RuntimeException
     */
    protected function prepareBase64($imageData, $filename = null)
    {
        $extension = $this->base64Extension($imageData);

        $tmp = wp_tempnam();

        if (file_put_contents($tmp, $this->decodeBase64($imageData)) === false) {
            @unlink($tmp);
            throw new RuntimeException('Failed to save image file.');
        }

        return [[
            'tmp_name' => $tmp,
            'name'     => $filename ?: uniqid('wpfluent-') . '.' . $extension,
        ], $tmp];
    }

    /**
     * The file extension for a base64 image's MIME type, such as jpg for
     * data:image/jpeg.
     *
     * @param  string $imageData
     * @param  string $default
     * @return string
     */
    protected function base64Extension($imageData, $default = 'png')
    {
        if (!preg_match('#^data:(image/[a-z0-9.+-]+)[;,]#i', $imageData, $matches)) {
            return $default;
        }

        $mime = strtolower($matches[1]);

        foreach (wp_get_mime_types() as $extensions => $type) {
            if ($type === $mime) {
                return strtok($extensions, '|');
            }
        }

        return $default;
    }

    /**
     * Decode a base64 image, with or without its data: prefix.
     *
     * @param  string $imageData
     * @return string
     * @throws RuntimeException
     */
    protected function decodeBase64($imageData)
    {
        if (strpos($imageData, 'base64,') !== false) {
            $imageData = explode('base64,', $imageData)[1];
        }

        $decoded = base64_decode($imageData);

        if (!$decoded) {
            throw new RuntimeException('Invalid base64 image data.');
        }

        return $decoded;
    }

    /**
     * Ensure required WordPress media functions are loaded.
     * 
     * @return void
     */
    protected function includeMediaFunctions()
    {
        if (!function_exists('media_handle_sideload')) {
            require_once ABSPATH . 'wp-admin/includes/image.php';
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';
        }
    }

    /**
     * Checks if a string is base64 encoded.
     * 
     * @param  string $string
     * @return boolean
     */
    protected function isBase64($string)
    {
        return is_string($string) && str_starts_with($string, 'data:image');
    }

    /**
     * Saves a base64 encoded image to a file and returns the URL.
     *
     * @param string $imageData The base64 encoded image string.
     * @param string $extension Optional. The image file extension.
     * @return string The URL of the saved image on success.
     * @throws RuntimeException If the image could not be saved.
     */
    public function base64ToUrl($imageData, $extension = 'png')
    {
        $decoded = $this->decodeBase64($imageData);

        $upload_dir = wp_upload_dir();

        if (!wp_mkdir_p($upload_dir['path'])) {
            throw new RuntimeException(
                'Upload directory does not exist and could not be created.'
            );
        }

        $filename = uniqid('wpfluent-') . '.' . $extension;
        $file_path = trailingslashit($upload_dir['path']) . $filename;
        $filUrl  = trailingslashit($upload_dir['url']) . $filename;

        if (file_put_contents($file_path, $decoded) === false) {
            throw new RuntimeException('Failed to save image file.');
        }

        return $filUrl;
    }

    /**
     * Delete the temporary file and throw an exception on failure.
     * 
     * @param  string $tmp
     * @param  int|\WP_Error $attachmentId
     * @return void
     */
    protected function cleanup($tmp, $attachmentId)
    {
        if ($tmp && file_exists($tmp)) {
            @unlink($tmp);
        }

        if (is_wp_error($attachmentId)) {
            throw new RuntimeException(
                'Failed to upload media: ' . $attachmentId->get_error_message()
            );
        }
    }

    /**
     * Generate and save attachment metadata (image sizes, dimensions, etc.)
     * 
     * @param  int $attachmentId
     * @return array
     */
    public function generateMetadata($attachmentId)
    {
        $this->includeMediaFunctions();

        $metadata = wp_generate_attachment_metadata(
            $attachmentId, get_attached_file($attachmentId)
        );

        wp_update_attachment_metadata(
            $attachmentId, $metadata
        );

        return $metadata;
    }

    /**
     * Update attachment title (post_title), description (post_content),
     * caption (post_excerpt) and alt text (alt). An empty alt clears it.
     * 
     * @param  int $attachmentId
     * @param  array  $fields      
     * @return void
     */
    public function updateAttachmentFields($attachmentId, array $fields = [])
    {
        $postFields = array_intersect_key($fields, array_flip([
            'post_title', 'post_content', 'post_excerpt',
        ]));

        if ($postFields) {
            wp_update_post(['ID' => $attachmentId] + $postFields);
        }

        if (array_key_exists('alt', $fields)) {
            update_post_meta(
                $attachmentId, '_wp_attachment_image_alt',
                sanitize_text_field((string) $fields['alt'])
            );
        }
    }

    /**
     * Get detailed info about an attachment.
     * 
     * @param  int $attachmentId
     * @return array
     */
    public function getAttachmentArray($attachmentId)
    {
        return [
            'id'   => $attachmentId,
            'url'  => wp_get_attachment_url($attachmentId),
            'path' => get_attached_file($attachmentId),
            'type' => get_post_mime_type($attachmentId),
        ];
    }

    /**
     * Delete an attachment.
     * 
     * @param  int  $attachmentId
     * @param  boolean $forceDelete
     * @return \WP_Post|false|null Post data on success, false/null on failure.
     */
    public function delete($attachmentId, $forceDelete = false)
    {
        $this->includeMediaFunctions();

        return wp_delete_attachment($attachmentId, $forceDelete);
    }

    /**
     * Set an image as featured for a post.
     * 
     * @param int $attachmentId
     * @param int $postId
     * @return int|bool
     */
    public function setAsFeaturedImage($attachmentId, $postId)
    {
        $this->includeMediaFunctions();
        
        return set_post_thumbnail($postId, $attachmentId);
    }

    /**
     * Find an attachment of any type by its file name.
     *
     * A file with exactly that name wins; otherwise the newest file whose
     * name contains it. The folder the file is in is not searched.
     *
     * @param string $name  Filename to search for (can be partial).
     * @return array|null   Attachment data array or null if not found.
     */
    public function findByFilename($name)
    {
        global $wpdb;

        $name = (string) $name;

        if ($name === '') {
            return null;
        }

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT p.ID, m.meta_value AS file FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} m
                ON m.post_id = p.ID AND m.meta_key = '_wp_attached_file'
             WHERE p.post_type = 'attachment'
             AND m.meta_value LIKE %s
             ORDER BY p.post_date DESC, p.ID DESC",
            '%' . $wpdb->esc_like($name) . '%'
        ));

        $found = null;

        foreach ($rows as $row) {
            $basename = wp_basename($row->file);

            if ($basename === $name) {
                $found = $row;
                break;
            }

            if (!$found && strpos($basename, $name) !== false) {
                $found = $row;
            }
        }

        return $found ? $this->getAttachmentArray((int) $found->ID) : null;
    }

    /**
     * Get all media attachments associated with a specific post; none
     * without a post id (null or '').
     *
     * @param int $postId
     * @return array  List of attachment data arrays.
     */
    public function findByPostId($postId)
    {
        // WordPress reads a missing parent as "any parent".
        if ($postId === null || $postId === '') {
            return [];
        }

        $attachments = get_children([
            'post_parent'    => $postId,
            'post_type'      => 'attachment',
            'post_status'    => 'inherit',
            'posts_per_page' => -1,
        ]);

        $results = [];

        foreach ($attachments as $attachment) {
            $results[] = $this->getAttachmentArray($attachment->ID);
        }

        return $results;
    }

    /**
     * Find attachments by MIME type, newest first.
     *
     * @param string $mimeType e.g. 'image/jpeg', 'application/pdf', etc.
     * @param int $limit The most to return; -1 for all.
     * @return array List of attachment data arrays.
     */
    public function findByMimeType($mimeType, $limit = 100)
    {
        $attachments = get_posts([
            'post_type'      => 'attachment',
            'post_status'    => 'inherit',
            'posts_per_page' => (int) $limit,
            'post_mime_type' => $mimeType,
        ]);

        return array_map(
            fn($attachment) => $this->getAttachmentArray($attachment->ID),
            $attachments
        );
    }

    /**
     * Find attachments uploaded within a date range.
     *
     * @param string $startDate Format: 'YYYY-MM-DD'
     * @param string $endDate   Format: 'YYYY-MM-DD'
     * @param int $limit The most to return; -1 for all.
     * @return array List of attachment data arrays.
     */
    public function findByDateRange($startDate, $endDate, $limit = 100)
    {
        $attachments = get_posts([
            'post_type'      => 'attachment',
            'post_status'    => 'inherit',
            'posts_per_page' => (int) $limit,
            'date_query'     => [
                [
                    'after'     => $startDate,
                    'before'    => $endDate,
                    'inclusive' => true,
                ]
            ],
        ]);

        return array_map(
            fn($attachment) => $this->getAttachmentArray($attachment->ID),
            $attachments
        );
    }
}
