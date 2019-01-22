<?php
class Driv_Richsnippets_Block_Jsonld extends Mage_Core_Block_Template
{
    public function getProduct()
    {
        $_product = Mage::registry('current_product');
        return ($_product && $_product->getEntityId()) ? $_product : false;
    }
    public function getAttributeValue($attr)
    {
        $value = null;
        $_product = $this->getProduct();
        if ($_product) {
            $type = $_product->getResource()->getAttribute($attr)->getFrontendInput();
            if ($type == 'text' || $type == 'textarea') {
                $value = $_product->getData($attr);
            } elseif ($type == 'select') {
                $value = $_product->getAttributeText($attr) ? $_product->getAttributeText($attr) : '';
            }
        }
        return $value;
    }
    public function getStructuredData()
    {
        // get product
        $_product = $this->getProduct();
        // check if $_product exists
        if ($_product) {
            $categoryName = Mage::registry('current_category') ? Mage::registry('current_category')->getName() : '';
            $productId = $_product->getEntityId();
            $storeId = Mage::app()->getStore()->getId();
            $currencyCode = Mage::app()->getStore()->getCurrentCurrencyCode();
            $json = array(
                'availability' => $_product->isAvailable() ? 'http://schema.org/InStock' : 'http://schema.org/OutOfStock',
                'category' => $categoryName
            );
            // check if reviews are enabled in extension's backend configuration
            $review = Mage::getStoreConfig('richsnippets/general/review');
            if ($review) {
                $reviewSummary = Mage::getModel('review/review/summary');
                $ratingData = Mage::getModel('review/review_summary')->setStoreId($storeId)->load($productId);
                // get reviews collection
                $reviews = Mage::getModel('review/review')
                    ->getCollection()
                    ->addStoreFilter($storeId)
                    ->addStatusFilter(1)
                    ->addFieldToFilter('entity_id', 1)
                    ->addFieldToFilter('entity_pk_value', $productId)
                    ->setDateOrder()
                    ->addRateVotes()
                    ->getItems();
                $reviewData = array();
                if (count($reviews) > 0) {
                    foreach ($reviews as $r) {
                        $ratings = array();
                        foreach ($r->getRatingVotes() as $vote) {
                            $ratings[] = $vote->getPercent();
                        }
                        $avg = array_sum($ratings) / count($ratings);
                        $avg = number_format(floor(($avg / 20) * 2) / 2, 1); // average rating (1-5 range)
                        $datePublished = explode(' ', $r->getCreatedAt());
                        // another "mini-array" with schema data
                        $reviewData[] = array(
                            '@type' => 'Review',
                            'author' => $this->htmlEscape($r->getNickname()),
                            'datePublished' => str_replace('/', '-', $datePublished[0]),
                            'name' => $this->htmlEscape($r->getTitle()),
                            'reviewBody' => nl2br($this->escapeHtml($r->getDetail())),
                            'reviewRating' => array(
                                '@type' => 'Rating',
                                'ratingValue' => $avg
                            )
                        );
                    }
                }
                // let's put review data into $json array
                $json['reviewCount'] = $reviewSummary->getTotalReviews($_product->getId(), true);
                $json['ratingValue'] = number_format(floor(($ratingData['rating_summary'] / 20) * 2) / 2, 1); // average rating (1-5 range)
                $json['review'] = $reviewData;
            }
            //use Desc if Shortdesc not work
            if ($_product->getShortDescription()) {
                $descsnippet = html_entity_decode(strip_tags($_product->getShortDescription()));
            } else {
                $descsnippet = Mage::helper('core/string')->substr(html_entity_decode(strip_tags($_product->getDescription())), 0, 165);
            }

            // Present all the data
            $data = array(
                '@context' => 'http://schema.org',
                '@type' => 'Product',
                'name' => $_product->getName(),
                'sku' => $_product->getSku(),
                'image' => $_product->getImageUrl(),
                'url' => $_product->getProductUrl(),
                'description' => $descsnippet, //use full description if short description is empty
                'offers' => array(
                    '@type' => 'Offer',
                    'availability' => $json['availability'],
                    'price' => number_format((float)$_product->getFinalPrice(), 2, '.', ''),
                    'priceCurrency' => $currencyCode,
                    'category' => $json['category'],
                    'url' => $_product->getProductUrl()
                )
            );

            // if reviews are enabled in magento admin - present it
            if ($review) {
                $data['aggregateRating'] = array(
                    '@type' => 'AggregateRating',
                    'bestRating' => '5',
                    'worstRating' => '0',
                    'ratingValue' => $json['ratingValue'],
                    'reviewCount' => $json['reviewCount']
                );
                $data['review'] = $reviewData;
            } 

            // This module has an admin interface, here is where those attributes are presented.
            // Get the attributes
            $attributes = Mage::getStoreConfig('richsnippets/attributes');

            // If ! empty, place them in an array
            foreach ($attributes AS $key => $value) {
                if ($value) {
                    $data[$key] = $this->getAttributeValue($value);
                }
            }
            // Return all the data which should be included in the schema json
//            return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            return $data;
        }
        return null;
    }
    public function getOpenGraphSnippet($structuredData)
    {
        $openGrapthSnippet = <<<OPENGRAPHSNIPPET
        
        <meta property="og:type" content="og:product">
        <meta property="og:title" content="{$structuredData['name']}">
        <meta property="og:image" content="{$structuredData['image']}">
        <meta property="og:description" content="{$structuredData['description']}">
        <meta property="og:url" content="{$structuredData['url']}">
OPENGRAPHSNIPPET;
        return $openGrapthSnippet;
    }
    public function jsonEncodePretty($input)
    {
        $version = phpversion();
        $version = explode('.', $version);
        $version = $version[0] . $version[1];
        $version = intval($version);
        // JSON Variables available only in PHP 5.4
        if ($version <= 53) {
            $result = json_encode($input);
        } else {
            $result = json_encode($input, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        }
        return $result;
    }
}