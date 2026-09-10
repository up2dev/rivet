<?php
/**
 * FileController class file
 *
 * PHP Version 8.1
 *
 * @category Controller
 * @package  Rivet\Http\Controllers\Storage
 * @license  https://opensource.org/licenses/MIT MIT License
 */
namespace Rivet\Http\Controllers\Storage;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Rivet\Data\Models\Storage\File as StorageFile;
use Rivet\Http\Controllers\BaseController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * FileController
 *
 * @category Controller
 * @package  Rivet\Http\Controllers\Storage
 * @license  https://opensource.org/licenses/MIT MIT License
 */
class FileController extends BaseController
{
    /**
     * Method called by the /storage/file/upload URL in DELETE.
     *
     * @param Request $request The request
     *
     * @return JsonResponse
     */
	public function upload(Request $request): JsonResponse
	{
        $meta = $request->input('meta');

        $this->setResponse(trans('rivet::storage.up_progress'), 200);

        if (
            StorageFile::chunk(
                $meta['name'],
                $request->input('order'),
                $request->file('chunk')
            ) === intval($meta['length'])
        ) {
            $file = StorageFile::build(
                $meta['name'], $meta['length']
            );
            $file['media_uid'] = $meta['media_uid'];
            $file['extension'] = $meta['extension'];

            $this->setResponse(
                $this->repo->create($file), 201
            );
        }

        return $this->response->format();
	}

    /**
     * Method called by the /storage/file/upload URL in DELETE.
     *
     * @param int     $token   The file token
     * @param int     $action  The stream action (show|down)
     * @param Request $request The request
     *
     * @return BinaryFileResponse|JsonResponse
     */
	public function stream(
        string $token, string $action, Request $request
    ): BinaryFileResponse|JsonResponse
	{
        $file = StorageFile::firstWhere('token', 'LIKE', $token);
        $is_errored = false;

        if (array_key_exists('width', $request->all())) {
            $width = intval($request->all()['width']);
            $file->setWidth($width);

            if (
                (
                    !is_null($file->media->min_width) &&
                    $file->media->min_width > $width
                ) || (
                    !is_null($file->media->max_width) &&
                    $file->media->max_width < $width
                )
            ) {
                $is_errored = true;

                $this->setResponse(trans('rivet::storage.wrong_width'), 400);
            }
        }

        if (array_key_exists('height', $request->all())) {
            $height = intval($request->all()['height']);
            $file->setHeight($height);

            if (
                (
                    !is_null($file->media->min_height) &&
                    $file->media->min_height > $height
                ) || (
                    !is_null($file->media->max_height) &&
                    $file->media->max_height < $height
                )
            ) {
                $is_errored = true;

                $this->setResponse(trans('rivet::storage.wrong_height'), 400);
            }
        }

        if (array_key_exists('croped', $request->all())) {
            $file->setIsCroped(boolval($request->all()['croped']));
        }

        if (array_key_exists('quality', $request->all())) {
            $file->setQuality(intval($request->all()['quality']));
        }

        if (!$is_errored) {
            $this->setResponse($file->variation_absolute_path, 200);

            if ($action === 'down') {
                $this->response->setHeader(
                    'Content-Disposition',
                    "attachment; filename=\"{$file->name}.{$file->extension}\""
                );
                // header("Content-Transfer-Encoding: Binary");
                // header("Content-Length:".filesize($attachment_location));
            }
        }

        return $this->response->format();
	}
}
