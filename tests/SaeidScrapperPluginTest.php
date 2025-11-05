<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class SaeidScrapperPluginTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        global $_saeid_scrapper_test_terms, $_saeid_scrapper_test_term_parents;

        $_saeid_scrapper_test_terms = [];
        $_saeid_scrapper_test_term_parents = [];
    }

    /**
     * Helper to call protected static methods on the plugin class.
     *
     * @param string $method Method name.
     * @param array  $args   Arguments to pass.
     * @return mixed
     */
    private function callProtected(string $method, array $args = [])
    {
        $ref = new ReflectionMethod(Saeid_Scrapper_Plugin::class, $method);
        $ref->setAccessible(true);

        return $ref->invokeArgs(null, $args);
    }

    public function testNormalizeCategoriesRemovesDuplicates(): void
    {
        $raw = [
            'خانه > محصولات > درایوها',
            'درایوها',
            'سری ATV310',
            ['name' => 'سری ATV310'],
            ' تجهیزات ',
        ];

        $normalized = $this->callProtected('normalize_categories', [$raw]);

        $this->assertSame([
            ['name' => 'درایوها', 'slug' => 'درایوها'],
            ['name' => 'سری ATV310', 'slug' => 'سری-atv310'],
            ['name' => 'تجهیزات', 'slug' => 'تجهیزات'],
        ], $normalized);
    }

    public function testParseProductCategoriesReadsBreadcrumbs(): void
    {
        $html = <<<HTML
        <html><body>
            <nav class="woocommerce-breadcrumb">
                <a href="https://tehran-control.com">خانه</a>
                <a href="https://tehran-control.com/product-category/siemens-products/">محصولات زیمنس</a>
                <a href="https://tehran-control.com/product-category/siemens-products/hmi/">صفحه نمایش HMI</a>
            </nav>
            <ul class="breadcrumb">
                <li><a href="#">محصولات > تجهیزات</a></li>
            </ul>
        </body></html>
        HTML;

        $document = new DOMDocument();
        libxml_use_internal_errors(true);
        $document->loadHTML($html);
        libxml_clear_errors();
        libxml_use_internal_errors(false);

        $xpath = new DOMXPath($document);
        $categories = $this->callProtected('parse_product_categories', [$xpath]);

        $this->assertSame([
            ['name' => 'محصولات زیمنس', 'slug' => 'siemens-products'],
            ['name' => 'صفحه نمایش HMI', 'slug' => 'hmi'],
            ['name' => 'تجهیزات', 'slug' => 'تجهیزات'],
        ], $categories);
    }

    public function testGetProductCategoryLabelsBuildsBreadcrumbs(): void
    {
        global $_saeid_scrapper_test_terms, $_saeid_scrapper_test_term_parents;

        $_saeid_scrapper_test_terms['99:product_cat'] = [
            (object) ['term_id' => 12, 'name' => 'صفحه نمایش HMI'],
            (object) ['term_id' => 25, 'name' => 'قطعات'],
        ];

        $_saeid_scrapper_test_term_parents['12:product_cat'] = 'محصولات زیمنس › صفحه نمایش HMI';
        $_saeid_scrapper_test_term_parents['25:product_cat'] = 'قطعات';

        $labels = $this->callProtected('get_product_category_labels', [99]);

        $this->assertSame([
            'محصولات زیمنس › صفحه نمایش HMI',
            'قطعات',
        ], $labels);
    }

    public function testParseProductAttributesPrefersTableMarkup(): void
    {
        $html = <<<HTML
        <html><body>
            <table class="woocommerce-product-attributes">
                <tr>
                    <th>توان خروجی</th>
                    <td><strong>5.5 کیلووات</strong></td>
                </tr>
            </table>
            <ul class="product-attributes">
                <li>جریان: 12 آمپر</li>
            </ul>
        </body></html>
        HTML;

        $document = new DOMDocument();
        libxml_use_internal_errors(true);
        $document->loadHTML($html);
        libxml_clear_errors();
        libxml_use_internal_errors(false);

        $xpath = new DOMXPath($document);
        $attributes = $this->callProtected('parse_product_attributes', [$xpath]);

        $this->assertSame([
            [
                'name'  => 'توان خروجی',
                'value' => '<strong>5.5 کیلووات</strong>',
            ],
        ], $attributes);
    }

    public function testMergeProductAttributesSkipsDuplicateLabels(): void
    {
        $existing = [
            ['name' => 'توان موتور', 'value' => '5.5 کیلووات'],
            ['name' => 'جریان', 'value' => '10 آمپر'],
        ];

        $new = [
            ['name' => 'توان موتور', 'value' => '7.5 کیلووات'],
            ['name' => 'درجه حفاظت', 'value' => 'IP20'],
        ];

        $merged = $this->callProtected('merge_product_attributes', [$existing, $new]);

        $this->assertSame([
            ['name' => 'توان موتور', 'value' => '5.5 کیلووات'],
            ['name' => 'جریان', 'value' => '10 آمپر'],
            ['name' => 'درجه حفاظت', 'value' => 'IP20'],
        ], $merged);
    }

    public function testExtractJsonLdProductDataFindsNestedProductNode(): void
    {
        $json = json_encode([
            '@context' => 'https://schema.org',
            '@graph'   => [
                [
                    '@type' => 'BreadcrumbList',
                ],
                [
                    '@type' => 'Product',
                    'name'  => 'درایو ATV310',
                    'sku'   => 'ATV310HU55N4E',
                    'category' => ['درایوها', 'ATV310'],
                ],
            ],
        ], JSON_UNESCAPED_UNICODE);

        $html = '<html><body><script type="application/ld+json">' . $json . '</script></body></html>';

        $document = new DOMDocument();
        libxml_use_internal_errors(true);
        $document->loadHTML($html);
        libxml_clear_errors();
        libxml_use_internal_errors(false);

        $product = $this->callProtected('extract_json_ld_product_data', [$document]);

        $this->assertSame([
            '@type'    => 'Product',
            'name'     => 'درایو ATV310',
            'sku'      => 'ATV310HU55N4E',
            'category' => ['درایوها', 'ATV310'],
        ], $product);
    }

    public function testParseAdditionalTablesCapturesSpecificationRows(): void
    {
        $html = <<<HTML
        <html><body>
            <div id="tab-additional_information">
                <table>
                    <tr><th>ابعاد</th><td>120 × 230 × 200 میلی‌متر</td></tr>
                </table>
            </div>
        </body></html>
        HTML;

        $document = new DOMDocument();
        libxml_use_internal_errors(true);
        $document->loadHTML($html);
        libxml_clear_errors();
        libxml_use_internal_errors(false);

        $xpath = new DOMXPath($document);
        $attributes = $this->callProtected('parse_additional_tables', [$xpath]);

        $this->assertSame([
            [
                'name'  => 'ابعاد',
                'value' => '120 × 230 × 200 میلی‌متر',
            ],
        ], $attributes);
    }
}
