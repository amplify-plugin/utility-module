<?php

namespace Amplify\System\Utility\Seeders;

use Amplify\System\Utility\Models\DataTransformation;
use Illuminate\Database\Seeder;

class DataTransformationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $data_transformations = [
            ['id' => '4', 'transformation_name' => 'Extract Lumber sizes', 'description' => 'This script extracts the width, thickness and length of the lumer product, storing them as attributes.   Where a fractional length occurs we will also create a decimal version of the length for use in the front end. Certain abbreviations are also expanded to make the product name more readable.', 'applies_to' => '{"name":"Products"}', 'in_category' => '[2]', 'execution_sequence' => '3', 'run_when' => '["On Demand"]', 'scripts' => 'If product_name matches "/([0-9]+)X([0-9]+)X(([0-9]+-[0-9]\\/[0-9])|([0-9]+))/"
{
     Extract product_name "/([0-9]+)X([0-9]+)X(([0-9]+-[0-9]\\/[0-9])|([0-9]+))/" $thickness $width $length
     Store $thickness Attribute Thickness
     Store $width Attribute Width
     Store $length Attribute Length
     fraction-to-decimal "-" $length $decimalLength
}
Replace product_name "DOUG FIR" "Douglas Fir"
Replace product_name "S4S" "Smooth 4 Sides"
Replace product_name "PRECUT PREMIUM" ""
Replace product_name "PRECUT" ""
Replace product_name "PREM PC" "Premium Pre-cut"
Replace product_name "KD" "Kiln Dried"
Convert_case product_name capitalize-all product_name', 'file_path' => 'public/data-transformation-files/2022-01-24-1643036844.txt', 'created_at' => '2022-01-11 21:08:06', 'updated_at' => '2022-02-01 21:00:17'],
            ['id' => '5', 'transformation_name' => 'Set Model code for Dewalt products', 'description' => 'This script simply extracts the DeWalt model code from the product name and stores it in the proper place.', 'applies_to' => '{"name":"Products"}', 'in_category' => '[11]', 'execution_sequence' => null, 'run_when' => '["On Demand"]', 'scripts' => 'if product_name matches "/dewalt (\\S+)/i"
{
    extract product_name "/dewalt (\\S+)/i" $model
    store $model field model_code
}', 'file_path' => 'public/data-transformation-files/2022-01-26-1643196732.txt', 'created_at' => '2022-01-18 17:53:23', 'updated_at' => '2022-01-26 17:32:12'],
            ['id' => '7', 'transformation_name' => 'Process screws', 'description' => 'This script extracts the gauge and length of screws, storing them as attributes.  It also extracts the pack size for the item and store that as an attribute.  Certain abbreviations are also expanded to make the product name more readable.', 'applies_to' => '{"name":"Products"}', 'in_category' => '[9]', 'execution_sequence' => '1', 'run_when' => '["On Demand","Save"]', 'scripts' => 'if product_name matches "/([0-9]+) X (([0-9]+-[0-9]\\/[0-9])|([0-9]+))/"
{
Extract product_name "/([0-9]+) X (([0-9]+-[0-9]\\/[0-9])|([0-9]+))/" $screwGauge $screwLength
store $screwGauge attribute screwGauge
store $screwLength attribute screwLength
}
replace product_name "PHIL FL" "Philips Flat Head"
replace product_name "FLAT" ""
if product_name matches "/[0-9]+\\/PK/"
{
extract product_name "/([0-9]+)\\/PK/" $packSize
store $packSize attribute packsize
}
Convert_case product_name capitalize-all product_name
classify "Screws"', 'file_path' => null, 'created_at' => '2022-01-20 18:04:56', 'updated_at' => '2022-02-14 07:28:31'],
            ['id' => '8', 'transformation_name' => 'Categorize Lumber', 'description' => null, 'applies_to' => '{"name":"Products"}', 'in_category' => '[2]', 'execution_sequence' => '2', 'run_when' => '["On Demand"]', 'scripts' => 'categorize "Constructional Lumber"', 'file_path' => 'public/data-transformation-files/2022-01-31-1643633673.txt', 'created_at' => '2022-01-27 20:48:53', 'updated_at' => '2022-01-31 18:54:33'],
            ['id' => '9', 'transformation_name' => 'BG - Categorize (remove) door closers', 'description' => null, 'applies_to' => '{"name":"Products"}', 'in_category' => '[]', 'execution_sequence' => null, 'run_when' => '["On Demand"]', 'scripts' => 'categorize remove "Door Closers"', 'file_path' => 'public/data-transformation-files/2022-02-01-1643728381.txt', 'created_at' => '2022-02-01 21:09:21', 'updated_at' => '2022-02-09 20:29:08'],
        ];

        DataTransformation::query()->insert($data_transformations);
    }
}
