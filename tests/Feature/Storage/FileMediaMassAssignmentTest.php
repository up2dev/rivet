<?php
/**
 * FileMediaMassAssignmentTest class file
 *
 * PHP Version 8.1
 *
 * @category Test
 * @package  Rivet\Tests\Feature\Storage
 * @license  https://opensource.org/licenses/MIT MIT License
 */
namespace Rivet\Tests\Feature\Storage;

use Rivet\Tests\TestCase;
use Rivet\Data\Repositories\Storage\MediaRepository;
use Rivet\Data\Repositories\Storage\FileRepository;
use Rivet\Data\Models\Storage\Media;

/**
 * Covers $fillable enforcement on File and Media, which directly
 * affects FileController::upload() and Media creation through
 * BaseController's generic 'add' action.
 *
 * @category Test
 * @package  Rivet\Tests\Feature\Storage
 * @license  https://opensource.org/licenses/MIT MIT License
 */
class FileMediaMassAssignmentTest extends TestCase
{
    /**
     * @return void
     */
    public function testMediaCanBeCreatedThroughItsRepository(): void
    {
        $repo = new MediaRepository();

        $repo->create([
            'uid'       => 'AVATAR',
            'name'      => 'Avatar',
            'max_chunk' => 1024,
            'mimetype'  => 'image/*'
        ]);

        $this->assertSame('AVATAR', $repo->getModel()->uid);
        $this->assertDatabaseHas('media', [
            'uid' => 'AVATAR', 'mimetype' => 'image/*'
        ]);
    }

    /**
     * Mirrors what FileController::upload() actually sends: the plain
     * fields from File::build() plus 'media_uid' (resolved through
     * CRUD's relation-association branch, not the fillable path - see
     * the comment on File::$fillable).
     *
     * @return void
     */
    public function testFileCanBeCreatedThroughItsRepositoryLinkedToAMedia(): void
    {
        $media = Media::create([
            'uid'       => 'DOCUMENT',
            'name'      => 'Document',
            'max_chunk' => 1024,
            'mimetype'  => 'application/pdf'
        ]);

        $repo = new FileRepository();

        $repo->create([
            'name'      => 'report',
            'token'     => 'abc123token',
            'extension' => 'pdf',
            'size'      => 4096,
            'media_uid' => $media->uid
        ]);

        $file = $repo->getModel();

        $this->assertSame('report', $file->name);
        $this->assertSame('abc123token', $file->token);
        $this->assertSame($media->id, $file->media_id);
        $this->assertDatabaseHas('files', [
            'token' => 'abc123token', 'media_id' => $media->id
        ]);
    }
}
