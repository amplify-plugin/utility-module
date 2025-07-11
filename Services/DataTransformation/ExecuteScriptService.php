<?php

namespace Amplify\System\Utility\Services\DataTransformation;

use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * @property $request
 */
class ExecuteScriptService
{
    public function __construct()
    {
        // echo 'ExecuteScriptService :: __construct <br>';
    }

    public function execute(Request $request): JsonResponse
    {
        $script = (array) $request->input('script');    // array ['script...']
        $product = (array) $request->input('product');   // array
        $attribute = (array) $request->input('attribute'); // array
        $variables = (array) $request->input('variables'); // array

        $variables = $variables ?? [
            'height' => '12',
            'width' => '11',
            'depth' => '43',
        ];

        $response = compact('attribute', 'product', 'variables');

        return response()->json($response);
    }

    /**
     * Set variable value in variables array
     *
     * @return void
     */
    private function setVar(&$variables, $varName, $varValue)
    {
        if (collect($variables)->where('name', '=', $varName)->isNotEmpty()) {
            foreach ($variables as &$variable) {
                if ($variable['name'] === $varName) {
                    $variable['value'] = $varValue;
                }
            }
        } else {
            $variables[] = [
                'name' => $varName,
                'value' => $varValue,
            ];
        }
    }

    /**
     * @return void
     */
    public function setAttrValue(&$attributes, string $attrName, string $value, bool $addMode, bool $allowMultiple, bool $attributeAlreadyExist)
    {
        // Sets the value of specified attribute
        // Check if there is already the same name and value...
        if (collect($attributes)->where('name', $attrName)->where('value', $value)->isEmpty()) {
            // check if addMode is true
            if ($addMode && ! $attributeAlreadyExist) {
                $updated = false;

                foreach ($attributes as &$attribute) {
                    if (strtolower($attribute['name']) === strtolower($attrName)) {
                        $attribute['value'] = $allowMultiple
                            ? [$value]
                            : $value;
                        $updated = true;
                        break;
                    }
                }
                if (! $updated) {
                    $attr['name'] = $attrName;
                    $attr['value'] = $allowMultiple
                        ? [$value]
                        : $value;
                    $attr['allow_multiple'] = $allowMultiple;
                    $attributes[] = $attr;
                }
            } else {
                // if not addMode, then update the value
                foreach ($attributes as &$attribute) {
                    if ($attrName === strtolower($attribute['name']) && $attribute['value'] !== $value) {
                        if ($allowMultiple) {
                            $attribute['value'][] = $value;
                        } else {
                            $attribute['value'] = $value;
                        }

                        return;
                    }
                }
            }
        }
    }

    private function getValue(&$fields, &$attributes, &$variables, string $name, bool &$found = true)
    {
        // Get's the value of the specified field/attribute
        // Is it variable?
        if (substr($name, 0, 1) === '$') {
            foreach ($variables as $variable) {
                if ($variable['name'] === $name) {
                    return $variable['value'];
                }
            }
            $found = false;

            return '';
        }
        // Is it a string constant?
        if (substr($name, 0, 1) === '"') {
            return stripslashes(substr(substr($name, 1), 0, -1));
        }

        // Is it a field?
        foreach ($fields as $field) {
            if ($field['name'] === $name) {
                return $field['value'];
            }
        }
        foreach ($attributes as $attribute) {
            if (strtolower($attribute['name']) === strtolower($name)) {
                return $attribute['value'];
            }
        }
        $found = false;

        return '';
    }

    private function setValue(&$fields, string $fieldName, string $value)
    {
        // Sets the value of specified field
        $isFieldFound = false;

        foreach ($fields as &$field) {
            if ($field['name'] === $fieldName) {
                $field['value'] = $fieldName === 'additional'
                    ? [$value]
                    : $value;
                $isFieldFound = true;
                break;
            }
        }

        if (! $isFieldFound) {
            $fields[] = [
                'name' => $fieldName,
                'value' => $fieldName === 'additional'
                    ? [$value]
                    : $value,
            ];
        }
    }

    /**
     * @param  array  $request
     * @return array|JsonResponse
     */
    public function validateScript($request)
    {
        $script = (array) $request['script'];
        $fields = (array) $request['fields'];
        $attributes = (array) $request['attributes'];
        $variables = (array) $request['variables'];
        $categories = (array) $request['categories'];
        $productClassification = (string) $request['productClassification'];
        $single = true;
        $inBlock = false;
        $skipping = false;
        $ifStatement = null;
        $error = '';
        $productFields = [
            'product_code',
            'product_name',
            'product_slug',
            'product_type',
            'products_list',
            'has_sku',
            'description',
            'ean_number',
            'gtin_number',
            'upc_number',
            'asin',
            'is_new',
            'is_updated',
            'manufacturer',
            'model_code',
            'model_name',
            'msrp',
            'model',
            'parent_id',
            'sku_id',
            'sku_default_attributes',
            'sku_part_number',
            'main',
            'thumbnail',
            'additional',
            'user_id',
            'product_classification_id',
            'status',
            'selling_price',
        ];

        if (count($script) > 1) {
            $single = false;
        }

        foreach ($script as $text) {
            $found = true;
            if (preg_match_all('/"(?:\\\\.|[^\\\\"])*"|\S+/', $text, $tokens)) {
                $tokens = $tokens[0];
                switch (strtolower($tokens[0])) {
                    case 'classify':
                        // classify statement
                        // format: classify classification-name
                        if ($inBlock && $skipping) {
                            break;
                        }
                        if (count($tokens) < 2) {
                            $error = 'Not enough parameters for CLASSIFY statement.';
                            break;
                        }
                        $inputField = $tokens[1];
                        $inputFieldValue = $this->getValue($fields, $attributes, $variables, $inputField, $found);
                        if (! $found) {
                            $error = 'Invalid input field specified for CLASSIFY statement.';
                            break;
                        }
                        $productClassification = $inputFieldValue;

                        break;
                    case 'categorize':

                        // categorize statement
                        // format: categorize category-name
                        if ($inBlock && $skipping) {
                            break;
                        }
                        if (count($tokens) < 2) {
                            $error = 'Not enough parameters for CATEGORIZE statement.';
                            break;
                        }
                        $inputField = $tokens[1];

                        if (strtolower($inputField) == 'remove') {
                            if (count($tokens) < 3) {
                                $error = 'Not enough parameters for CATEGORIZE statement.';
                                break;
                            }
                            $inputField = $tokens[2];
                            $inputFieldValue = $this->getValue($fields, $attributes, $variables, $inputField, $found);

                            for ($i = 0; $i < count($categories); $i++) {
                                if ($categories[$i] == $inputFieldValue) {
                                    // Remove from list...
                                    array_splice($categories, $i, 1);
                                    break;
                                }
                            }
                            break;
                        }
                        $inputFieldValue = $this->getValue($fields, $attributes, $variables, $inputField, $found);
                        if (! $found) {
                            $error = 'Invalid input field specified for CATEGORIZE statement.';
                            break;
                        }
                        // check if already in list of categories...
                        foreach ($categories as $category) {
                            if ($category == $inputFieldValue) {
                                break 2;
                            }
                        }
                        $categories[] = $inputFieldValue;

                        break;

                    case 'extract':
                        // extract statement
                        // format: extract input-field regex $var1 $var2 ...
                        if ($inBlock && $skipping) {
                            break;
                        }
                        $inputField = $tokens[1];
                        $inputFieldValue = $this->getValue($fields, $attributes, $variables, $inputField, $found);
                        if (! $found) {
                            $error = 'Invalid input field specified for EXTRACT statement.';
                            break;
                        }
                        if (count($tokens) < 4) {
                            $error = 'Not enough parameters for EXTRACT statement.';
                            break;
                        }

                        $regex = rtrim(ltrim($tokens[2], '"'), '"');

                        if (preg_match($regex, $inputFieldValue, $matches)) {
                            $numMatches = count($matches);

                            $varIndex = 3;

                            for ($i = 1; $i < $numMatches; $i++) {
                                if ($varIndex < count($tokens)) {
                                    $this->setVar($variables, $tokens[$varIndex], $matches[$i]);
                                }
                                $varIndex++;
                            }
                        } else {
                            $error = 'No match found for regex in EXTRACT statement.';
                            break;
                        }
                        break;

                    case 'store':
                        // store statement
                        // format: store input-field field|attribute output-field [ADD|REPLACE]
                        $addMode = false;
                        $allowMultiple = false;
                        $attributeAlreadyExist = false;
                        if ($inBlock && $skipping) {
                            break;
                        }
                        $inputField = $tokens[1];
                        $inputFieldValue = $this->getValue($fields, $attributes, $variables, $inputField, $found);

                        if (! $found) {
                            $error = 'Invalid input field specified for STORE statement.';
                            break;
                        }

                        if (strtolower($tokens[2]) == 'attribute') {
                            //  Storing as attribute...
                            // Check if an ADD/REPLACE parameter exists...
                            if (count($tokens) == 5) {
                                $addMode = false;
                                $attributeAllowsMultipleValues = false;
                                if (strtolower($tokens[4]) == 'add') {
                                    if ($this->checkIfAttributeAllowsMultipleValues($attributes, strtolower($tokens[3]))) {
                                        $allowMultiple = true;
                                    }
                                    if ($this->checkIfAttributeAlreadyExist($attributes, strtolower($tokens[3]))) {
                                        $attributeAlreadyExist = true;
                                    }

                                    $addMode = true;
                                } elseif (strtolower($tokens[4]) == 'replace') {
                                    $addMode =
                                        ! (bool) array_search(strtolower($tokens[3]), array_column($attributes, 'name'));
                                }
                            }

                            if (count($tokens) == 4) {
                                $addMode =
                                    ! (bool) array_search(strtolower($tokens[3]), array_column($attributes, 'name'));
                            }
                        }
                        // Check output field name is valid...
                        // It could be a field name or attribute name...
                        $isField = false;
                        $outputField = $tokens[3];

                        foreach ($productFields as $fieldName) {
                            if ($outputField == $fieldName) {
                                // Update field
                                $this->setValue($fields, $outputField, $inputFieldValue);
                                break;
                            }
                        }

                        $this->setAttrValue($attributes, $outputField, $inputFieldValue, $addMode, $allowMultiple, $attributeAlreadyExist);

                        break;

                    case 'replace':
                        // replace statement
                        // format: replace input-field searchString replaceString [GLOBAL] [IGNORE-CASE]
                        if ($inBlock && $skipping) {
                            break;
                        }
                        if (count($tokens) < 4) {
                            $error = 'Not enough parameters for REPLACE command';
                            break;
                        }
                        $inputField = $tokens[1];
                        $inputFieldValue = $this->getValue($fields, $attributes, $variables, $inputField, $found);
                        if (! $found) {
                            $error = 'Invalid input field specified for REPLACE statement.';
                            break;
                        }
                        $searchString = stripslashes(substr(substr($tokens[2], 1), 0, -1));
                        $replaceString = stripslashes(substr(substr($tokens[3], 1), 0, -1));

                        $ignoreCase = false;
                        if (count($tokens) > 4) {
                            if (strtolower($tokens[4] = 'ignore-case')) {
                                $ignoreCase = true;
                            }
                        }
                        if ($ignoreCase) {
                            $newValue = str_ireplace($searchString, $replaceString, $inputFieldValue);
                        } else {
                            $newValue = str_replace($searchString, $replaceString, $inputFieldValue);
                        }
                        $this->setValue($fields, $inputField, $newValue);
                        break;

                    case 'create-slug':
                        // create-slug statement
                        // format: create-slug field_name $slug
                        if ($inBlock && $skipping) {
                            break;
                        }
                        if (count($tokens) < 3) {
                            $error = 'Not enough parameters for create-slug command';
                            break;
                        }
                        $inputField = $tokens[1];
                        $variableName = $tokens[2];
                        $inputFieldValue = $this->getValue($fields, $attributes, $variables, $inputField, $found);
                        $newValue = $this->getSlug($inputFieldValue, $request['productData']);

                        if (! $found) {
                            $error = 'Invalid input field specified for create-slug statement.';
                            break;
                        }

                        $this->setVar($variables, $variableName, $newValue);

                        break;
                    case 'fraction-to-decimal':
                        // convert-to-decimal statement
                        // format: fraction-to-decimal separator-char input-field variable
                        if ($inBlock && $skipping) {
                            break;
                        }
                        if (count($tokens) < 4) {
                            $error = 'Not enough parameters for CONVERT-TO-DECIMAL command';
                            break;
                        }
                        $separator = stripslashes(substr(substr($tokens[1], 1), 0, -1));
                        $inputField = $tokens[2];
                        $variableName = $tokens[3];
                        $inputFieldValue = $this->getValue($fields, $attributes, $variables, $inputField, $found);
                        if (! $found) {
                            $error = 'Invalid input field specified for FRACTION-TO-DECIMAL statement.';
                            break;
                        }
                        $regex = '/([0-9]+)'.$separator."([0-9]+)\/([0-9]+)/";
                        if (preg_match($regex, $inputFieldValue, $matches)) {
                            $wholeNumber = $matches[1];
                            $numerator = $matches[2];
                            $denominator = $matches[3];
                            $newValue = $wholeNumber + ($numerator / $denominator);
                            $this->setVar($variables, $variableName, $newValue);
                        }

                        break;
                    case 'concat':
                        // concat statement
                        // format: concat output-var input1 input2 input3 ... inputN
                        if ($inBlock && $skipping) {
                            break;
                        }
                        if (count($tokens) < 4) {
                            $error = 'Not enough parameters for CONCAT command';
                            break;
                        }
                        $separator = stripslashes(substr(substr($tokens[1], 1), 0, -1));
                        $inputField = $tokens[2];
                        $variableName = $tokens[1];
                        $newValue = '';
                        for ($i = 2; $i < count($tokens); $i++) {
                            $inputFieldValue = $this->getValue($fields, $attributes, $variables, $tokens[$i], $found);
                            if (! $found) {
                                $error = 'Invalid input field specified for CONCAT statement.';
                                break 2;
                            }
                            $newValue = $newValue.$inputFieldValue;
                        }
                        $this->setVar($variables, $variableName, $newValue);

                        break;
                    case 'convert_case':
                        // convert-case statement
                        // format: convert-case input-field CAPITALIZE-ALL|CAPITALIZE-FIRST|UPPER-CASE|LOWER-CASE

                        if ($inBlock && $skipping) {
                            break;
                        }
                        $inputField = $tokens[1];
                        $inputFieldValue = $this->getValue($fields, $attributes, $variables, $inputField, $found);

                        if (! $found) {
                            $error = 'Invalid input field specified for CONVERT-CASE statement.';
                            break;
                        }

                        switch (strtolower($tokens[2])) {
                            case 'capitalize-all':
                                $newValue = ucwords(strtolower($inputFieldValue));
                                break;
                            case 'capitalise-first':
                                $newValue = ucfirst($inputFieldValue);
                                break;
                            case 'upper-case':
                                $newValue = strtoupper($inputFieldValue);
                                break;
                            case 'lower-case':
                                $newValue = strtolower($inputFieldValue);
                                break;
                            default:
                                $newValue = $inputFieldValue;
                                break;
                        }
                        if (substr($inputField, 0, 1) == '$') {
                            $this->setVar($variables, $inputField, $newValue);
                        } else {
                            $this->setValue($fields, $inputField, $newValue);
                        }

                        break;
                    case '{':
                        // block start...
                        $inBlock = true;
                        // format: convert-case input-field CAPITALIZE-ALL|CAPITALIZE-FIRST|UPPER-CASE|LOWER-CASE
                        break;

                    case '}':
                        // block end...
                        $inBlock = false;
                        // format: convert-case input-field CAPITALIZE-ALL|CAPITALIZE-FIRST|UPPER-CASE|LOWER-CASE
                        break;

                    case 'if':
                        // if statement
                        // format: if input-field MATCHES|NOT-MATCHES regex
                        // check there are enough parameters...
                        $skipping = false;
                        if (count($tokens) != 4) {
                            $error = 'Not enough parameters for IF statement.';
                            break;
                        }
                        // check input field can be found...
                        $inputField = $tokens[1];
                        //                  \Log::info($tokens);
                        $inputFieldValue = $this->getValue($fields, $attributes, $variables, $inputField, $found);
                        if (! $found) {
                            $error = 'Invalid input field specified for IF statement.';
                            break;
                        }

                        $matchType = (strtolower($tokens[2]) == 'matches');
                        $regex = rtrim(ltrim($tokens[3], '"'), '"');

                        $match = preg_match($regex, $inputFieldValue);

                        $match = (preg_match($regex, $inputFieldValue) === 1);

                        $ifStatement = ($match === $matchType);
                        if ($single) {
                            break;
                        }
                        if (! $ifStatement) {
                            $skipping = true;
                        }
                        break;

                    case 'convert-to-decimal':
                        // convert-to-decimal statement
                        // format: convert-to-decimal separator-char input-field variable
                        if ($inBlock && $skipping) {
                            break;
                        }
                        if (count($tokens) < 4) {
                            $error = 'Not enough parameters for CONVERT-TO-DECIMAL command';
                            break;
                        }
                        $separator = stripslashes(substr(substr($tokens[1], 1), 0, -1));
                        $inputField = $tokens[2];
                        $variableName = $tokens[3];
                        $inputFieldValue = $this->getValue($fields, $attributes, $variables, $inputField, $found);
                        if (! $found) {
                            $error = 'Invalid input field specified for CONVERT-TO-DECIMAL statement.';
                            break;
                        }
                        $regex = '([0-9]+)'.$separator."([0-9]+)\/([0-9]+)|([0-9]+)";
                        if (preg_match($regex, $inputFieldValue, $matches)) {
                            $wholeNumber = $matches[1];
                            $numerator = $matches[2];
                            $denominator = $matches[3];
                            $newValue = $wholeNumber + ($numerator / $denominator);
                            $this->setVar($variables, $variableName, $newValue);
                        }
                        break;
                }
            }
        }

        return compact(
            'attributes', 'fields', 'variables',
            'categories', 'productClassification', 'ifStatement', 'error'
        );
    }

    public function getSlug($inputFieldValue, $product)
    {
        $slug = Str::slug($inputFieldValue);
        $product = Product::whereProductSlug($slug)->where('id', '!=', $product['Product_Id'])->first();

        if (! is_null($product)) {
            $splitNumberFromSlug = explode('-p', $inputFieldValue);
            $slug = $this->getProperSlug($splitNumberFromSlug);

            return $this->getSlug($slug, $product); // recursively check for the unique slug
        }

        return $slug;
    }

    public function getProperSlug($splitNumberFromSlug)
    {
        if (count($splitNumberFromSlug) < 2) {
            return $splitNumberFromSlug[0].'-p1';
        }

        $slugNumber = ++$splitNumberFromSlug[count($splitNumberFromSlug) - 1];
        $slug_name = "{$splitNumberFromSlug[0]}"."-p{$slugNumber}";

        return $slug_name;
    }

    /**
     * @return bool
     */
    public function checkIfAttributeAllowsMultipleValues($attributes, $attributeName)
    {
        foreach ($attributes as $attribute) {
            if (strtolower($attribute['name']) === strtolower($attributeName)) {
                return (bool) $attribute['allow_multiple'];
            }
        }

        return false;
    }

    /**
     * @return bool
     */
    public function checkIfAttributeAlreadyExist($attributes, $attributeName)
    {
        foreach ($attributes as $attribute) {
            if (strtolower($attribute['name']) === strtolower($attributeName)) {
                return true;
            }
        }

        return false;
    }
}
