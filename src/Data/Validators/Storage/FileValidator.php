<?php
/**
 * FileValidator class file
 *
 * PHP Version 8.1
 *
 * @category Validator
 * @package  Rivet\Data\Validators\Storage
 * @license  https://opensource.org/licenses/MIT MIT License
 */
namespace Rivet\Data\Validators\Storage;

use App\Data\Models\Utilities\Media;
use Illuminate\Support\Facades\Request;
use Rivet\Data\Models\Storage\File as StorageFile;
use Rivet\Data\Models\Storage\Media as StorageMedia;
// use Illuminate\Support\Facades\Input;
use Rivet\Data\Validators\Validator;

/**
 * FileValidator
 *
 * @category Validator
 * @package  Rivet\Data\Validators\Storage
 * @license  https://opensource.org/licenses/MIT MIT License
 */
class FileValidator extends Validator
{
    /**
     * The set of rules of the Validator.
     *
     * @var array
     */
    protected $rules = [
        'meta.name'      => [ 'string', 'required' ],
        'meta.size'      => [ 'integer', 'required' ],
        'meta.extension' => [ 'string', 'required' ],
        'meta.length'    => [ 'integer', 'required' ],
        'chunk'          => [ 'file', 'required' ],
        'order'          => [
            'integer', 'required', 'min:1', 'lte:meta.length'
        ],
        'meta.media_uid' => [ 'string', 'required', 'exists:media,uid' ]
    ];

    /**
     * Build a validation and store the results in attributes.
     *
     * @param array $fields  The fields to validate
     * @param int   $uid     The unique ID of the model (is PUT/PATCH)
     * @param bool  $is_mass Is this a mass validation ?
     */
    public function __construct(
        array $fields, ?int $uid = null
    ) {
        $chunk_size = $kb_size = config('storage.chunk_size');
        $chunk_size *= 1024;
        $meta = Request::get('meta');
        $order = Request::get('order');
        $media = null;

        if (!is_null($meta)) {
            if (array_key_exists('media_uid', $meta)) {
                $media = StorageMedia::firstWhere('uid', 'LIKE', $meta['media_uid']);
            }

            if (array_key_exists('size', $meta)) {
                $length = ceil($meta['size'] / $chunk_size);
                $this->rules['meta.length'][] = "size:{$length}";
            }

            if (array_key_exists('length', $meta) && !is_null($order)) {
                $this->rules['chunk'][] = (
                    ($meta['length'] === $order)?
                        "max:{$kb_size}": "size:{$kb_size}"
                );
            }
        }

        if (!is_null($media) && !is_null($media->mimetype)) {
            $extensions = StorageFile::apacheMimeTypes($media->mimetype);

            foreach ($extensions as $mime => $ext) {
                $extensions[$mime] = implode(',', $ext);
            }

            $extensions = implode(',', $extensions);

            $this->rules['meta.extension'][] = "in:{$extensions}";
        }

        if (!is_null($media) && !is_null($media->max_chunk)) {
            $max_size = $chunk_size * $media->max_chunk;

            $this->rules['meta.size'][] = "max:{$max_size}";
        }

        parent::__construct($fields, $uid);
    }
}
