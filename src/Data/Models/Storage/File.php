<?php

/**
 * File class file
 *
 * PHP Version 8.1
 *
 * @category Model
 * @package  Rivet\Data\Models\Storage
 * @license  https://opensource.org/licenses/MIT MIT License
 */
namespace Rivet\Data\Models\Storage;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File as FacadesFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Rivet\Data\Models\BaseModel;
use Rivet\Database\Factories\Storage\FileFactory;

/**
 * File
 *
 * @category Model
 * @package  Rivet\Data\Models\Storage
 * @license  https://opensource.org/licenses/MIT MIT License
 */
class File extends BaseModel
{
    use HasFactory, FileTrait, SoftDeletes;

    /**
     * The uid associated with the model log.
     *
     * @var string
     */
    public $log_uid = 'File';

    /**
     * The attributes that are mass assignable. 'media_id' is
     * deliberately not listed: it is resolved through CRUD's
     * relation-association pattern (matches `_u?id$` against
     * 'media_uid'), not mass-assigned as a plain field.
     *
     * @var array
     */
    protected $fillable = [ 'name', 'token', 'extension', 'size' ];

    /**
     * The relationships that should always be loaded.
     *
     * @var array
     */
    protected $with = [ /*'media'*/ ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = [ 'mimetype' ];

    /**
     * The attributes excluded from the model's JSON form.
     *
     * @var array
     */
    protected $hidden = [ 'deleted_at', 'media_id' ];

    /**
     * The width of an image.
     *
     * @var int
     */
    protected $width = null;

    /**
     * The height of an image.
     *
     * @var int
     */
    protected $height = null;

    /**
     * Is an image croped on resize.
     *
     * @var bool
     */
    protected $is_croped = true;

    /**
     * Quality of the image
     *
     * @var bool
     */
    protected $quality = 95;

    /**
     * Create a new factory instance for the model.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    protected static function newFactory(): Factory
    {
        return FileFactory::new();
    }

    /**
     * -------------------------------------------------------------------------
     * Relations
     * -------------------------------------------------------------------------
     */

    /**
     * Get the File's Media.
     *
     * @return BelongsTo
     */
    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class)->without('files');
    }

    /**
     * -------------------------------------------------------------------------
     * Mutators
     * -------------------------------------------------------------------------
     */

    /**
     * Get the mimetype.
     *
     * @return string
     */
    public function getMimetypeAttribute(): string
    {
        return FacadesFile::mimeType($this->original_absolute_path);
    }

    /**
     * Get the absolute path.
     *
     * @return string
     */
    public function getOriginalAbsolutePathAttribute(): string
    {
        return Storage::disk(config('storage.disk'))->path(
            config('storage.dir') . "/{$this->token}"
        );
    }

    /**
     * Get the absolute path.
     *
     * @return string
     */
    public function getVariationAbsolutePathAttribute(): string
    {
        $path = $this->original_absolute_path;
        $mimetypes = [];
        list($width, $height) = getimagesize($path)?: [ null, null ];

        // This mimetype-detection logic belongs in a validator, not
        // here.
        if (extension_loaded('imagick')) {
            $mimetypes = array_filter(File::apacheMimeTypes(
                (new \Imagick())->queryFormats()
            ), function ($extension, $mimetype) {
                return Str::startsWith($mimetype, 'image');
            }, ARRAY_FILTER_USE_BOTH);
        } elseif (extension_loaded('gd')) {
            foreach (gd_info() as $type => $is_supported) {
                if (
                    $is_supported &&
                    !Str::contains($type, 'Create') &&
                    Str::lower(Str::afterLast($type, ' ')) === 'support' &&
                    !in_array(Str::lower(Str::before($type, ' ')), $mimetypes)
                ) {
                    $mimetypes[] = Str::lower(Str::before($type, ' '));
                }
            }

            $mimetypes = File::apacheMimeTypes($mimetypes);
        }

        if (in_array(
            FacadesFile::mimeType($path), array_keys($mimetypes)
        ) && ((
            !is_null($this->width) && $this->width > 0 &&
            $this->width !== $width
        ) || (
            !is_null($this->height) && $this->height > 0 &&
            $this->height !== $height
        ))) {
            $name = config('storage.dir') . "/{$this->token}-";
            $name .= (is_null($this->width)? $width: $this->width);
            $name .= '-';
            $name .= (is_null($this->height)? $height: $this->height);
            $name .= $this->is_croped? '-c': '-nc';
            $old_path = $path;
            $path = Storage::disk(config('storage.disk'))->path($name);

            if (!Storage::disk(config('storage.disk'))->exists($name)) {
                if (extension_loaded('imagick')) {
                    $imagick = new \Imagick($old_path);
                    $new_ratio = $this->width / $this->height;
                    $old_ratio = $width / $height;

                    if ($this->is_croped && $new_ratio !== $old_ratio) {
                        $width_ratio = $this->width / $width;
                        $height_ratio = $this->height / $height;
                        $x_crop = abs(
                            ($new_ratio > $old_ratio)?
                                0: (($this->width - ($width * $height_ratio)) / 2) / $height_ratio
                        );
                        $y_crop = abs(
                            ($new_ratio > $old_ratio)?
                                (($this->height - ($height * $width_ratio)) / 2) / $width_ratio: 0
                        );
                        $width = $width - ($x_crop * 2);
                        $height = $height - ($y_crop * 2);

                        $imagick->cropImage($width, $height, $x_crop, $y_crop);
                    }

                    $imagick->scaleImage($this->width, $this->height);
                    $imagick->writeImage($path);
                } elseif (extension_loaded('gd')) {
                    $source = imagecreatefromstring(
                        file_get_contents($old_path)
                    );
                    $mime_type = exif_imagetype($old_path);
                    $old_width = imagesx($source);
                    $old_height = imagesy($source);
                    $new_ratio = $this->width / $this->height;
                    $old_ratio = $old_width / $old_height;

                    if ($old_ratio > $new_ratio) {
                        $new_width =  $this->width;
                        $new_height = $this->width / $old_ratio;
                    } else {
                        $new_height = $this->height;
                        $new_width = $this->height * $old_ratio;
                    }

                    $image = imagecreatetruecolor($new_width, $new_height);

                    if ($this->is_croped) {
                        if ($old_ratio > $new_ratio) {
                            $croped_width = $old_height * $new_ratio;
                            $croped_height = $old_height;
                            $x = ($old_width - $croped_width) / 2;
                            $y = 0;
                        } else {
                            $croped_width = $old_width;
                            $croped_height = $old_width / $new_ratio;
                            $x = 0;
                            $y = ($old_height - $croped_height) / 2;
                        }

                        imagecopyresampled(
                            $image, $source, 0, 0, $x, $y,
                            $this->width, $this->height, $croped_width, $croped_height
                        );
                    } else {
                        imagecopyresampled(
                            $image, $source, 0, 0, 0, 0,
                            $new_width, $new_height, $old_width, $old_height
                        );
                    }

                    switch ($mime_type) {
                        case IMAGETYPE_JPEG:
                            $image = imagejpeg($image, $path, $this->quality);
                            break;
                        case IMAGETYPE_PNG:
                            $image = imagepng($image, $path, $this->quality);
                            break;
                        case IMAGETYPE_GIF:
                            $image = imagegif($image, $path);
                            break;
                        case IMAGETYPE_WEBP:
                            $image = imagewebp($image, $path, $this->quality);
                            break;
                    }

                    imagedestroy($source);
                    imagedestroy($image);
                }
            }
        }

        return $path;
    }

    /**
     * Set the image width.
     *
     * @param int $width The image width
     *
     * @return void
     */
    public function setWidth(int $width): void
    {
        $this->width = $width;
    }

    /**
     * Set the image height.
     *
     * @param int $height The image height
     *
     * @return void
     */
    public function setHeight(int $height): void
    {
        $this->height = $height;
    }

    /**
     * Set the image quality.
     *
     * @param int $quality The image quality
     *
     * @return void
     */
    public function setQuality(int $quality): void
    {
        $this->quality = $quality;
    }

    /**
     * Set the image crop.
     *
     * @param int $is_croped The image crop
     *
     * @return void
     */
    public function setIsCroped(bool $is_croped): void
    {
        $this->is_croped = $is_croped;
    }

    /**
     * Remove all local files.
     *
     * @return void
     */
    public function remove(): void
    {
        $this->prune(0);

        // Storage::delete() expects a path RELATIVE to the disk root,
        // unlike the File facade used in prune() just above (which
        // operates on real absolute filesystem paths). Passing
        // $this->original_absolute_path here - built via
        // Storage::disk()->path(), an absolute OS path - meant this
        // call never matched any real file: found by writing
        // FileCleanupOnDeleteTest, which failed even after the
        // bootFileTrait() rename confirmed remove() was finally being
        // called at all.
        Storage::disk(config('storage.disk'))->delete(
            config('storage.dir') . "/{$this->token}"
        );
    }

    /**
     * Remove all local files.
     *
     * @param int|null $variations_keped The number of variations to keep (default: config.storage.max_variations)
     *
     * @return int
     */
    public function prune(?int $variations_keped = null): int
    {
        $deleted = 0;

        $variations_keped = (
            is_null($variations_keped)?
                config('storage.max_variations'): $variations_keped
        );
        $files = FacadesFile::glob(
            "{$this->original_absolute_path}-$"
        );

        usort($files, function ($file_a, $file_b) {
            return filemtime($file_b) - filemtime($file_a);
        });

        for ($i = $variations_keped; $i < count($files); $i++) {
            $deleted += FacadesFile::delete($files[$i])? 1: 0;
        }

        return $deleted;
    }

    /**
     * Get MimeTypes from Apache config.storage.mimetypes_src \
     * The file must be formated like follow: \
     * \# This is a comment \
     * mime/type1 ext1 \
     * mime/type-2 ext21 ext22 \
     * mime/type.3      ext31 ext32 ext33 \
     * The function differenciate between mimtypes and extensions wit the "/". \
     * Mimetypes filters contains "/" (exemples: image/jpeg, image/*, * /*)
     *
     * @param string|array|null $filters A list of wildcarded mimtypes or extensions separeted by commas or as array
     *
     * @return array
     */
    public static function apacheMimeTypes(string|array|null $filters = null): array
    {
        $file = config('storage.mimetypes_src');

        $mimetypes = [];
        // $keys = [];
        $content = explode("\n", file_get_contents($file));

        foreach ($content as $value) {
            if (
                isset($value[0]) && $value[0] !== '#' &&
                preg_match_all('#([^\s]+)#', $value, $out) &&
                isset($out[1]) && ($c = count($out[1])) > 1
            ) {
                $value = Str::replace(
                    ' ', ',', Str::replaceFirst(
                        ' ', '@', preg_replace('/(?:\s|\t)+/', ' ', $value)
                    )
                );
                $mimetypes[] = $value;
            }
        }

        if (!is_null($filters)) {
            if (is_array($filters)) {
                $filters = join(',', $filters);
            }

            $filters = strtr(strtolower($filters), [
                ' ' => '', '+' => '\\+', '/' => '\\/', ',' => '$|^',
                '*' => '(?:.)+?', '.' => '\\.', '-' => '\\-'
            ]);
            $filters = preg_replace(
                '/((?:^|\\\|\\^)[A-z0-9]+\\/[A-z0-9\.\-]+)(\\$|$)/',
                '$1@.+$2', $filters
            );
            $filters = preg_replace(
                '/(^|\\\|\\^)([A-z0-9]+)(?:\\$|$)/',
                '$1.+?(?:,|@)$2(?:,.+?$|$)', $filters
            );

            $mimetypes = preg_grep("/^{$filters}$/", $mimetypes);
        }

        foreach ($mimetypes as $key => $mimetype) {
            $mimetypes[Str::before($mimetype, '@')] = explode(
                ',', Str::after($mimetype, '@')
            );
            unset($mimetypes[$key]);
        }

        ksort($mimetypes, SORT_STRING);

        return $mimetypes;
    }

    /**
     * Add a blob to the tmp folder.
     *
     * @param string       $name  The file name
     * @param int          $order The chunk number
     * @param UploadedFile $file  The chunk
     *
     * @return int
     */
    public static function chunk(
        string $name, int $order, UploadedFile $file
    ): int
    {
        $sname = self::systemFriendly($name);
        $dir = config('storage.dir') . "/tmp/{$sname}/";

        $file->storeAs($dir, "chunk-{$order}.tmp", config('storage.disk'));

        return count(Storage::disk(config('storage.disk'))->allFiles($dir));
    }

    /**
     * Rebuild a file from blobs.
     *
     * @param string      $name   The file name
     * @param int         $length The number of chunk
     * @param string|null $token  The token
     *
     * @return array
     */
    public static function build(
        string $name, int $length, ?string $token = null
    ): array
    {
        $sname = self::systemFriendly($name);
        $dir = config('storage.dir') . "/tmp/{$sname}/";

        if (is_null($token)) {
            do {
                $token = self::systemFriendly(uniqid());
            } while (!is_null(File::firstWhere('token', $token)));
        }

        $file = config('storage.dir') . "/{$token}";

        for ($i = 1; $i <= $length; $i++) {
            $chunk = Storage::disk(config('storage.disk'))->get("{$dir}chunk-{$i}.tmp");

            if ($i === 1) {
                Storage::disk(config('storage.disk'))->put($file, $chunk);
            } else {
                $content = Storage::disk(config('storage.disk'))->get($file);
                $content .= $chunk;

                Storage::disk(config('storage.disk'))->put($file, $content);
            }
        }

        Storage::disk(config('storage.disk'))->deleteDirectory($dir);

        return [
            'name' => $name,
            'token' => $token,
            'size' => Storage::disk(config('storage.disk'))->size($file)
        ];
    }

    /**
     * Rebuild a file from blobs.
     *
     * @param string $str the str to transform
     *
     * @return string
     */
    public static function systemFriendly(string $str): string
    {
        $str = base64_encode($str);
        $str = Str::after(strtr($str, '+/=', '000'), 'Temp');

        return $str;
    }
}
