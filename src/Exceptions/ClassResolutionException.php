<?php
/**
 * ClassResolutionException class file
 *
 * PHP Version 8.1
 *
 * @category Exception
 * @package  Rivet\Exceptions
 * @license  https://opensource.org/licenses/MIT MIT License
 */
namespace Rivet\Exceptions;

use RuntimeException;

/**
 * ClassResolutionException
 *
 * Thrown by ns_search()-dependent code (BaseController's repository
 * resolution, CRUD's relation-to-repository resolution) when the
 * naming-convention lookup fails to find a matching class.
 *
 * Before this modernization pass, a failed lookup returned null
 * silently and the real error only surfaced later as a generic
 * "call to a member function on null" a few frames away from the
 * actual cause. This exception is raised at the point of failure,
 * naming the class that was searched for.
 *
 * @category Exception
 * @package  Rivet\Exceptions
 * @license  https://opensource.org/licenses/MIT MIT License
 */
class ClassResolutionException extends RuntimeException
{
    /**
     * Build the exception for a failed ns_search() lookup.
     *
     * @param string $from   The namespace we searched from
     * @param string $target The kind of class we were looking for
     *                       (e.g. 'repository', 'model')
     *
     * @return self
     */
    public static function forTarget(string $from, string $target): self
    {
        return new self(
            "Could not resolve a '{$target}' class from '{$from}' by ".
            'naming convention. Check that the class exists and follows '.
            "the Http\\Controllers -> Data\\Repositories (or ->Data\\Models) ".
            'mirroring, or pass it explicitly to the constructor.'
        );
    }
}
