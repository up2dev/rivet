<?php
/**
 * FileTrait class file
 *
 * PHP Version 8.1
 *
 * @category Model
 * @package  Rivet\Data\Models\Auth
 * @license  https://opensource.org/licenses/MIT MIT License
 */
namespace Rivet\Data\Models\Storage;

/**
 * FileTrait
 *
 * @category Model
 * @package  Rivet\Data\Models\Auth
 * @license  https://opensource.org/licenses/MIT MIT License
 */
trait FileTrait
{
    /**
     * Boot the trait.
     *
     * Renamed from the original 'bootEssaiFichesModel': Eloquent's
     * bootTraits() only calls a method named boot{TraitBasename} - for
     * this trait, 'bootFileTrait'. The old name didn't match that
     * convention, so this listener was NEVER actually registered -
     * deleting a File silently never cleaned up its physical file on
     * disk. Found while reviewing the Storage module before writing
     * its tests, not by running anything yet.
     *
     * @return void
     */
    protected static function bootFileTrait(): void
    {
        static::deleting(function (File $model) {
            $model->remove();
        });
    }
}
