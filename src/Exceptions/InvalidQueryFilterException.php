<?php
/**
 * InvalidQueryFilterException class file
 *
 * PHP Version 8.1
 *
 * @category Exception
 * @package  Rivet\Exceptions
 * @license  https://opensource.org/licenses/MIT MIT License
 */
namespace Rivet\Exceptions;

use InvalidArgumentException;

/**
 * InvalidQueryFilterException
 *
 * Thrown by BuildsFilterConditions when a request's ?filters=/?sort=
 * string references an unknown bitwise operator, an unknown or
 * forbidden search operator, or a filter key not declared on the
 * repository. Previously these cases were silently ignored, letting
 * malformed input pass through as a no-op filter instead of a clear
 * error.
 *
 * @category Exception
 * @package  Rivet\Exceptions
 * @license  https://opensource.org/licenses/MIT MIT License
 */
class InvalidQueryFilterException extends InvalidArgumentException
{
    /**
     * A condition's bitwise operator is neither 'n' (AND) nor 'u' (OR).
     *
     * @param string $bitwise The offending bitwise operator
     *
     * @return self
     */
    public static function unknownBitwise(string $bitwise): self
    {
        return new self("Unknown filter bitwise operator '{$bitwise}'.");
    }

    /**
     * A 'btw' (between) operator's value did not split into exactly
     * two comma-separated bounds.
     *
     * @param string $value The offending raw value
     *
     * @return self
     */
    public static function invalidBetweenValue(string $value): self
    {
        return new self(
            "Invalid 'btw' filter value '{$value}': expected exactly two ".
            'comma-separated bounds.'
        );
    }

    /**
     * An 'in' operator's value produced no candidates to match against.
     *
     * @param string $value The offending raw value
     *
     * @return self
     */
    public static function invalidInValue(string $value): self
    {
        return new self(
            "Invalid 'in' filter value '{$value}': expected at least one ".
            'comma-separated value.'
        );
    }

    /**
     * The search operator itself is not a recognised one.
     *
     * @param string $operator The offending operator
     *
     * @return self
     */
    public static function unknownOperator(string $operator): self
    {
        return new self("Unknown filter operator '{$operator}'.");
    }

    /**
     * The filter key is not declared on the repository's $filters.
     *
     * @param string $key The offending filter key
     *
     * @return self
     */
    public static function unknownFilterKey(string $key): self
    {
        return new self("Unknown filter key '{$key}'.");
    }

    /**
     * The operator is explicitly forbidden for this filter key.
     *
     * @param string $key      The filter key
     * @param string $operator The forbidden operator
     *
     * @return self
     */
    public static function forbiddenOperator(string $key, string $operator): self
    {
        return new self(
            "Operator '{$operator}' is not allowed on filter key '{$key}'."
        );
    }
}
