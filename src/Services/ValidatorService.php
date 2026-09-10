<?php
/**
 * ValidatorService class file
 *
 * PHP Version 8.1
 *
 * @category Service
 * @package  Rivet\Services
 * @license  https://opensource.org/licenses/MIT MIT License
 */
namespace Rivet\Services;

use Illuminate\Support\Facades\Validator;

/**
 * ValidatorService
 *
 * @category Service
 * @package  Rivet\Services
 * @license  https://opensource.org/licenses/MIT MIT License
 */
abstract class ValidatorService
{
    /**
     * The state of mass assignment.
     *
     * @var bool
     */
    protected $is_mass = false;

    /**
     * The set of rules of the Validator child.
     *
     * @var array
     */
    protected $rules = [];

    /**
     * The final Validator validation state when created
     *
     * @var bool
     */
    protected $is_validated = true;

    /**
     * The final Validator errors when created
     *
     * @var array
     */
    protected $errors = null;

    /**
     * Build a validation and store the results in attributes.
     *
     * @param array $fields  The fields to validate
     */
    public function __construct(array $fields)
    {
        $this->is_mass = !is_assoc_array($fields);
        $fields = $this->is_mass? $fields: [$fields];

        if (empty($fields)) {
            $fields[] = [
                'has_error_probabilities' => true
            ];
        }

        foreach ($fields as $key => $subfields) {
            $validator = Validator::make($subfields, $this->rules);
            // $validator = $this->make($subfields);

            if ($validator->fails()) {
                if ($this->is_validated) {
                    $this->errors = [];
                }

                $this->is_validated = false;

                $this->errors = array_merge(
                    $this->errors,
                    $this->_beautifyErrors(
                        $validator->errors()->toArray(), $key
                    )
                );
            }
        }
    }

    /**
     * Get the Validator rules.
     *
     * @return array
     */
    public function getRules(): array
    {
        return $this->rules;
    }

    /**
     * Get a specific Validator rule.
     *
     * @param string $name  the targeted rule.
     * @param array  $unset the rules to ignore
     *
     * @return array
     */
    public function getRule(string $name, array $unset = []): array
    {
        $rules = [];

        foreach ($this->rules[$name] as $rule) {
            if (!in_array($rule, $unset)) {
                $rules[] = $rule;
            }
        }

        return $rules;
    }

    /**
     * Get the Validator validation state.
     *
     * @return bool|null
     */
    public function isValidated(): ?bool
    {
        return $this->is_validated;
    }

    /**
     * Get the Validator errors.
     *
     * @return array|null
     */
    public function getErrors(): ?array
    {
        return $this->errors;
    }

    // /**
    //  * Validate the fields.
    //  *
    //  * @param array $fields An array of the fields to validate.
    //  *
    //  * @return array
    //  */
    // protected function make(array $fields): Validation\Validator
    // {
    //     return Validator::make($fields, $this->rules);
    // }

    /**
     * Beautify the errors.
     *
     * @param array $errors An array of the errors to process.
     * @param int   $key    The current assignement key.
     *
     * @return array
     */
    private function _beautifyErrors(array $errors, int $key): array
    {
        foreach ($errors as $k => $error) {
            if ($this->is_mass) {
                $errors["{$key}:{$k}"] = $error;
                unset($errors[$k]);
            }

            $tmp_k = explode('.', $k);
            // transform array validation in array of errors
            if (count($tmp_k) > 1) {
                foreach ($error as $key => $e) {
                    $errors[$k][$key] = str_replace(
                        $k, $tmp_k[count($tmp_k) - 1], $e
                    );
                }

                // array_set($errors, $k, $error);
                $tmp_error = $errors[$k];

                foreach (array_reverse($tmp_k) as $key => $value) {
                    $tmp_error = [$value => $tmp_error];
                }

                $errors = array_merge_recursive($errors, $tmp_error);
                unset($errors[$k]);
            }
        }

        return $errors;
    }
}
