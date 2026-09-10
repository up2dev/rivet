<?php
/**
 * FileCleanupOnDeleteTest class file
 *
 * PHP Version 8.1
 *
 * @category Test
 * @package  Rivet\Tests\Feature\Storage
 * @license  https://opensource.org/licenses/MIT MIT License
 */
namespace Rivet\Tests\Feature\Storage;

use Rivet\Tests\TestCase;
use Rivet\Data\Models\Storage\Media;
use Rivet\Data\Models\Storage\File;
use Illuminate\Support\Facades\Storage;

/**
 * FileCleanupOnDeleteTest
 *
 * Characterizes File::remove() being wired up (or not) on model
 * deletion, through FileTrait's boot method. Before the rename fix
 * (bootEssaiFichesModel -> bootFileTrait), Eloquent never called this
 * listener at all - deleting a File left its physical file on disk
 * forever.
 *
 * This may still fail even after that rename:
 * getOriginalAbsolutePathAttribute() returns a full OS filesystem
 * path (Storage::disk()->path(...)), and remove() passes that
 * absolute path straight to Storage::disk()->delete(), which expects
 * a path RELATIVE to the disk root - a second, separate bug if so.
 * Left for the actual test run to confirm rather than fixed by
 * guesswork.
 *
 * @category Test
 * @package  Rivet\Tests\Feature\Storage
 * @license  https://opensource.org/licenses/MIT MIT License
 */
class FileCleanupOnDeleteTest extends TestCase
{
    /**
     * @return void
     */
    public function testDeletingAFileRemovesItsPhysicalFileFromDisk(): void
    {
        Storage::fake(config('storage.disk'));

        $media = Media::create([
            'uid'       => 'DOCUMENT',
            'name'      => 'Document',
            'max_chunk' => 1024,
            'mimetype'  => 'application/pdf'
        ]);

        $token = 'cleanup-test-token';
        $relative_path = config('storage.dir')."/{$token}";

        Storage::disk(config('storage.disk'))->put($relative_path, 'content');

        $file = new File([
            'name'      => 'report',
            'token'     => $token,
            'extension' => 'pdf',
            'size'      => 7
        ]);
        $file->media()->associate($media);
        $file->save();

        $this->assertTrue(
            Storage::disk(config('storage.disk'))->exists($relative_path)
        );

        $file->delete();

        Storage::disk(config('storage.disk'))->assertMissing($relative_path);
    }
}
