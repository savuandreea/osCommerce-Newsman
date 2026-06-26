<?php

namespace common\extensions\NewsMAN;

class NewsMAN extends \common\classes\modules\ModuleExtensions
{

    public static function allowed()
    {
        return self::enabled();
    }

    public static function adminActionIndex()
    {
        $view = '';
        if (isset($_GET['newsman_view'])) {
            $view = preg_replace('/[^a-z_]/', '', $_GET['newsman_view']);
        }

        if ($view === 'products') {
            $html = self::adminActionProducts();
        } elseif ($view === 'customers') {
            $html = self::adminActionCustomers();
        } elseif ($view === 'sync') {
            $html = self::adminActionSync();
        } else {
            $feedUrl = self::productFeedUrl();
            $syncEnabled = self::cfg('NEWSMAN_ENABLE_SYNC', '0') === '1';
            $apiReady = false;
            if (self::cfg('NEWSMAN_API_USER_ID') !== '') {
                if (self::cfg('NEWSMAN_API_KEY') !== '') {
                    if (self::cfg('NEWSMAN_LIST_ID') !== '') {
                        $apiReady = true;
                    }
                }
            }

            if ($apiReady) {
                $status = 'ready';
            } else {
                $status = 'missing API user ID or API key';
            }

            if ($syncEnabled) {
                $syncText = 'enabled';
            } else {
                $syncText = 'disabled';
            }

            $html = '<h3>NewsMAN</h3>' .
                '<p>Product feed and address sync for this shop.</p>' .
                '<p><strong>Product feed:</strong> <a target="_blank" href="' . self::h($feedUrl) . '">' . self::h($feedUrl) . '</a></p>' .
                '<p><strong>Address sync:</strong> ' . $syncText . ' (' . self::h($status) . ')</p>' .
                '<p>' .
                '<a class="btn btn-primary" target="_blank" href="' . self::h($feedUrl) . '">Open product feed</a> ' .
                '<a class="btn btn-default" href="?module=NewsMAN&newsman_view=products">Preview products</a> ' .
                '<a class="btn btn-default" href="?module=NewsMAN&newsman_view=customers">Preview addresses</a> ' .
                '<a class="btn btn-default" href="?module=NewsMAN&newsman_view=sync">Address sync</a>' .
                '</p>';
        }

        return $html;
    }
    public static function adminActionProducts()
    {
        return '<h3>NewsMAN product feed</h3>' .
            '<p>Use this public URL in NewsMAN feeds:</p>' .
            '<p><a target="_blank" href="' . self::h(self::productFeedUrl()) . '">' . self::h(self::productFeedUrl()) . '</a></p>' .
            '<pre>' . self::h(self::productFeedCsv()) . '</pre>';
    }

    public static function adminActionCustomers()
    {
        return '<h3>NewsMAN address export preview</h3>' .
            '<p>Preview only. Nothing is sent to NewsMAN from this page.</p>' .
            '<pre>' . self::h(self::addressCsv(self::getCustomers())) . '</pre>';
    }

    public static function adminActionSync()
    {
        if (isset($_GET['run'])) {
            $runSync = $_GET['run'] === '1';
        } else {
            $runSync = false;
        }

        if ($runSync) {
            $result = self::syncAddressesToNewsman();
            return '<h3>NewsMAN address sync</h3>' . self::syncResultHtml($result) .
                '<p><a class="btn btn-default" href="?module=NewsMAN&newsman_view=sync">Back to preview</a></p>';
        }

        return '<h3>NewsMAN address sync</h3>' .
            '<p>This sends customers and their default addresses to NewsMAN as subscriber properties.</p>' .
            '<p><a class="btn btn-primary" href="?module=NewsMAN&newsman_view=sync&run=1">Sync addresses now</a></p>' .
            '<pre>' . self::h(self::addressCsv(self::getCustomers(20))) . '</pre>';
    }

    public static function getFrontendHooks()
    {
        $path = \Yii::getAlias('@common') . DIRECTORY_SEPARATOR .
            'extensions' . DIRECTORY_SEPARATOR .
            'NewsMAN' . DIRECTORY_SEPARATOR .
            'hooks' . DIRECTORY_SEPARATOR;

        return [
            [
                'sort_order' => 100,
                'page_name' => 'frontend/layouts-main',
                'page_area' => 'before-body-close',
                'extension_file' => $path . 'frontend.footer.tpl',
            ],
            [
                'sort_order' => 100,
                'page_name' => 'frontend/layouts-ajax',
                'page_area' => 'before-body-close',
                'extension_file' => $path . 'frontend.footer.tpl',
            ],
        ];
    }

    public static function renderTrackingScript()
    {
        $id = self::cfg('NEWSMAN_REMARKETING_ID');
        if ($id === '') {
            return '';
        } else {
            $id = self::h($id);
        }

        return <<<HTML
<script type="text/javascript">
    window.dataLayer = window.dataLayer || [];
    var _nzm = _nzm || [];
    var _nzm_config = _nzm_config || [];
    (function(w, d, e, f, c, l, n) {
        if (w.__newsmanTrackingLoaded) return;
        w.__newsmanTrackingLoaded = true;
        ["identify", "track", "run"].map(function(m) {
            w[f][m] = function() {
                w[f].push([m].concat([].slice.call(arguments)));
            };
        });
        l = d.createElement(e);
        l.async = 1;
        l.src = (w[c].js_prefix || "https://t.newsmanapp.com") + "/jt/t.js";
        l.setAttribute("data-site-id", "$id");
        n = d.getElementsByTagName(e)[0];
        n.parentNode.insertBefore(l, n);
    })(window, document, "script", "_nzm", "_nzm_config");
</script>
HTML;
    }
    public static function renderFrontendEventsScript()
    {
        if (self::cfg('NEWSMAN_ENABLE_EVENTS', '1') !== '1') {
            return '';
        }

        $currencyJson = json_encode(self::currency(), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);

        return <<<HTML
<script type="text/javascript">
(function(){
  window.dataLayer = window.dataLayer || [];
  var newsmanCurrency = $currencyJson;
  function parseProductJsonLd(){
    var scripts = document.querySelectorAll('script[type="application/ld+json"]');
    for (var i = 0; i < scripts.length; i++) {
      try {
        var data = JSON.parse(scripts[i].textContent);
        var items = Array.isArray(data) ? data : [data];
        for (var j = 0; j < items.length; j++) {
          if (items[j]) {
            if (items[j]["@type"] === "Product") {
              return items[j];
            }
          }
        }
      } catch(e) {}
    }
    return null;
  }
  function normaliseCartProduct(product, index){
    var id = product.id || product.products_id || product.products_model || product.model || product.sku || product.name || product.products_name || '';
    var name = product.name || product.products_name || product.title || '';
    var price = product.price || product.products_price || product.final_price || product.special_price || 0;
    if (typeof price === 'string') price = price.replace(/[^0-9.,-]/g, '').replace(',', '.');
    var quantity = product.quantity || product.qty || product.products_quantity || 1;
    return {
      item_id: String(id),
      item_name: String(name),
      price: parseFloat(price || 0),
      quantity: parseInt(quantity || 1, 10) || 1,
      index: index + 1
    };
  }
  function getEntryDataCartProducts(){
    if (typeof window.entryData === 'undefined' || !window.entryData.productListings || !window.entryData.productListings.cart) {
      return [];
    }
    var products = window.entryData.productListings.cart.products || [];
    if (!Array.isArray(products)) products = Object.values(products);
    return products;
  }
  function pushCartView(){
    if (window.__newsmanCartViewSent) {
      return;
    }
    if ((window.location.pathname + window.location.search).match(/shopping-cart(?:$|[/?#])/) === null) {
      return;
    }
    var rawProducts = getEntryDataCartProducts();
    if (!rawProducts.length) {
      return;
    }
    var items = rawProducts.map(normaliseCartProduct).filter(function(item){ return item.item_id || item.item_name; });
    if (!items.length) {
      return;
    }
    var value = items.reduce(function(sum, item){ return sum + ((parseFloat(item.price) || 0) * (parseInt(item.quantity, 10) || 1)); }, 0);
    window.dataLayer.push({event: 'view_cart', ecommerce: {currency: newsmanCurrency, value: value, items: items}});
    window.dataLayer.push({event: 'set_cart', ecommerce: {currency: newsmanCurrency, value: value, items: items}});
    window.__newsmanCartViewSent = true;
  }
  function pushProductView(){
    if (window.__newsmanViewItemSent) return;
    var p = parseProductJsonLd();
    if (!p || !p.offers) return;
    var item = {
      item_id: String(p.sku || p.productID || p.name || ""),
      item_name: String(p.name || ""),
      price: parseFloat(p.offers.price || 0),
      quantity: 1
    };
    var currencyEl = document.querySelector('[itemprop="priceCurrency"]');
    var currency = p.offers.priceCurrency || (currencyEl ? currencyEl.getAttribute('content') : '') || '';
    window.dataLayer.push({event: "view_item", ecommerce: {currency: currency, value: item.price, items: [item]}});
    window.__newsmanViewItemSent = true;
  }
  function runNewsmanFrontendEvents(){
    pushProductView();
    pushCartView();
  }
  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", runNewsmanFrontendEvents);
  } else {
    runNewsmanFrontendEvents();
  }
})();
</script>
HTML;
    }
    public static function renderProductViewEvent($id, $name, $price = '0')
    {
        return self::renderProductEvent('detail', $id, $name, $price, 1);
    }
    public static function renderAddToCartEvent($id, $name, $price = '0', $quantity = 1)
    {
        return self::renderProductEvent('add', $id, $name, $price, $quantity);
    }
    public static function renderRemoveFromCartEvent($id, $name, $price = '0', $quantity = 1)
    {
        return self::renderProductEvent('remove', $id, $name, $price, $quantity);
    }
    public static function renderPurchaseEvent($orderId, $total, $products = [])
    {
        return self::renderEvent('purchase', [
            'purchase' => [
                'actionField' => [
                    'id' => $orderId,
                    'revenue' => $total,
                ],
                'products' => $products,
            ],
        ]);
    }
    public static function renderPurchaseOnSuccessEvent()
    {
        if (isset($_SERVER['REQUEST_URI'])) {
            $uri = (string) $_SERVER['REQUEST_URI'];
        } else {
            $uri = '';
        }

        if (isset($_GET['order_id'])) {
            $orderId = (int) $_GET['order_id'];
        } elseif (isset($_GET['orders_id'])) {
            $orderId = (int) $_GET['orders_id'];
        } else {
            $orderId = 0;
        }

        if (self::cfg('NEWSMAN_ENABLE_EVENTS', '1') !== '1') {
            return '';
        } elseif (strpos($uri, 'checkout/success') === false) {
            return '';
        } elseif ($orderId <= 0) {
            return '';
        }

        $purchase = self::getPurchaseData($orderId);
        if ($purchase) {
            return self::renderPurchaseEvent($orderId, $purchase['total'], $purchase['products']);
        } else {
            return '';
        }
    }
    public static function productFeedCsv($limit = 5000)
    {
        return self::makeProductFeedCsv(self::getProducts($limit));
    }

    public static function productFeedUrl()
    {
        return self::siteUrl('/newsman-products-feed.php?shop=' . rawurlencode(self::shopPath()));
    }

    public static function makeProductFeedCsv($products)
    {
        if (self::cfg('NEWSMAN_ENABLE_FEED', '1') !== '1') {
            return "feed disabled\n";
        }

        $csv = "id,title,description,availability,condition,price,link,image_link,brand\n";

        foreach ($products as $product) {
            $stock = (int) self::value($product, 'stock');
            $price = (float) self::value($product, 'price');

            if ($stock > 0) {
                $availability = 'in stock';
            } else {
                $availability = 'out of stock';
            }

            $csv .= self::csvValue(self::value($product, 'id')) . ',' .
                self::csvValue(self::value($product, 'name')) . ',' .
                self::csvValue(self::value($product, 'description')) . ',' .
                self::csvValue($availability) . ',' .
                self::csvValue('new') . ',' .
                self::csvValue(number_format($price, 2, '.', '') . ' ' . self::currency()) . ',' .
                self::csvValue(self::value($product, 'url')) . ',' .
                self::csvValue(self::value($product, 'image')) . ',' .
                self::csvValue('PrintShop') . "\n";
        }

        return $csv;
    }

    public static function makeAddressCsv($addresses)
    {
        return self::addressCsv($addresses);
    }

    public static function addressCsv($addresses)
    {
        $columns = ['email', 'first_name', 'last_name', 'phone', 'company', 'address', 'address2', 'postcode', 'city', 'state', 'country', 'newsletter'];
        $csv = implode(',', $columns) . "\n";

        foreach ($addresses as $address) {
            $line = [];
            foreach ($columns as $column) {
                $line[] = self::csvValue(self::value($address, $column));
            }
            $csv .= implode(',', $line) . "\n";
        }

        return $csv;
    }

    public static function syncAddressesToNewsman($limit = 100)
    {
        if (self::cfg('NEWSMAN_ENABLE_SYNC', '0') !== '1') {
            return ['ok' => false, 'message' => 'Address sync is disabled in module settings.', 'sent' => 0, 'errors' => []];
        }

        if (self::cfg('NEWSMAN_API_USER_ID') === '' || self::cfg('NEWSMAN_API_KEY') === '' || self::cfg('NEWSMAN_LIST_ID') === '') {
            return ['ok' => false, 'message' => 'Fill NewsMAN user ID, API key and list ID before syncing.', 'sent' => 0, 'errors' => []];
        }

        $sent = 0;
        $errors = [];
        foreach (self::getCustomers($limit) as $row) {
            if (!empty($row['error'])) {
                $errors[] = $row['error'];
                continue;
            }
            if (empty($row['email'])) {
                continue;
            }
            $result = self::sendSubscriber($row);
            if ($result['ok']) {
                $sent++;
            } else {
                $errors[] = $row['email'] . ': ' . $result['message'];
            }
        }

        return ['ok' => count($errors) === 0, 'message' => 'Sync finished.', 'sent' => $sent, 'errors' => $errors];
    }

    private static function renderEvent($action, $ecommerce)
    {
        if (self::cfg('NEWSMAN_ENABLE_EVENTS', '1') !== '1') {
            return '';
        } else {
            $eventName = self::h('newsman_' . $action);
            $json = json_encode($ecommerce, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);

            return '<script>window.dataLayer=window.dataLayer||[];window.dataLayer.push({' .
                '"event":"' . $eventName . '",' .
                '"ecommerce":' . $json .
                '});</script>';
        }
    }

    private static function renderProductEvent($action, $id, $name, $price = '0', $quantity = 1)
    {
        return self::renderEvent($action, [
            $action => [
                'products' => [[
                    'id' => $id,
                    'name' => $name,
                    'price' => $price,
                    'quantity' => $quantity,
                ]],
            ],
        ]);
    }
    private static function csvValue($value)
    {
        $value = (string) $value;
        $value = html_entity_decode($value, ENT_QUOTES, 'UTF-8');
        $value = strip_tags($value);
        $value = str_replace(["
", "
", "	"], ' ', $value);
        $value = preg_replace('/[[:space:]]+/', ' ', $value);
        $value = trim($value);

        if ($value === '') {
            return '""';
        } else {
            return '"' . str_replace('"', '""', $value) . '"';
        }
    }

    private static function value($row, $key)
    {
        if (isset($row[$key])) {
            return $row[$key];
        }

        if (isset($row['error'])) {
            return $row['error'];
        }

        return '';
    }

    private static function getPurchaseData($orderId)
    {
        $orderId = (int) $orderId;
        if ($orderId <= 0) {
            return null;
        }

        $totalRows = self::query(
            "select value as total from orders_total " .
            "where orders_id = " . $orderId . " and class = 'ot_total' limit 1"
        );
        if (empty($totalRows) || isset($totalRows[0]['error'])) {
            return null;
        }

        $productRows = self::query(
            "select products_id as id, products_name as name, final_price as price, " .
            "products_quantity as quantity from orders_products " .
            "where orders_id = " . $orderId
        );
        if (empty($productRows) || isset($productRows[0]['error'])) {
            return null;
        }

        $products = [];
        foreach ($productRows as $row) {
            $products[] = [
                'id' => self::value($row, 'id'),
                'name' => self::value($row, 'name'),
                'price' => self::value($row, 'price'),
                'quantity' => self::value($row, 'quantity'),
            ];
        }

        return [
            'total' => self::value($totalRows[0], 'total'),
            'products' => $products,
        ];
    }

    private static function getProducts($limit = 5000)
    {
        $limit = max(1, (int) $limit);
        $sql = "select p.products_id as id, pd.products_name as name, pd.products_description as description, " .
            "p.products_price as price, p.products_quantity as stock, p.products_image as image " .
            "from products p " .
            "left join products_description pd on pd.products_id = p.products_id " .
            "where coalesce(p.products_status, 1) = 1 " .
            "group by p.products_id " .
            "order by p.products_id desc limit " . $limit;

        $rows = self::query($sql);
        $products = [];

        foreach ($rows as $row) {
            if (isset($row['error'])) {
                return $rows;
            }
            $name = strip_tags((string) $row['name']);
            $slug = strtolower($name);
            $slug = preg_replace('/[^a-z0-9]+/i', '-', $slug);
            $slug = trim($slug, '-');

            $products[] = [
                'id' => $row['id'],
                'name' => $name,
                'description' => trim(strip_tags((string) $row['description'])),
                'url' => self::shopUrl('/' . $slug),
                'image' => self::imageUrl((string) $row['image']),
                'price' => $row['price'],
                'stock' => $row['stock'],
            ];
        }

        return $products;
    }

    private static function getCustomers($limit = 500)
    {
        $limit = max(1, (int) $limit);
        $sql = "select c.customers_email_address as email, c.customers_firstname as first_name, " .
            "c.customers_lastname as last_name, c.customers_telephone as phone, c.customers_newsletter as newsletter, " .
            "ab.entry_company as company, ab.entry_street_address as address, ab.entry_suburb as address2, " .
            "ab.entry_postcode as postcode, ab.entry_city as city, ab.entry_state as state, co.countries_name as country " .
            "from customers c " .
            "left join address_book ab on ab.customers_id = c.customers_id and ab.address_book_id = c.customers_default_address_id " .
            "left join countries co on co.countries_id = ab.entry_country_id " .
            "order by c.customers_id desc limit " . $limit;

        return self::query($sql);
    }

    private static function sendSubscriber($row)
    {
        $userId = self::cfg('NEWSMAN_API_USER_ID');
        $apiKey = self::cfg('NEWSMAN_API_KEY');
        $listId = self::cfg('NEWSMAN_LIST_ID');
        $email = self::value($row, 'email');

        if (!$userId || !$apiKey || !$listId || !$email) {
            return ['ok' => false, 'message' => 'Missing API credentials, list ID or email.'];
        }

        $props = [
            'phone' => self::value($row, 'phone'),
            'company' => self::value($row, 'company'),
            'address' => self::value($row, 'address'),
            'city' => self::value($row, 'city'),
            'state' => self::value($row, 'state'),
            'country' => self::value($row, 'country'),
            'newsletter' => self::value($row, 'newsletter'),
        ];

        $url = 'https://ssl.newsman.app/api/1.2/rest/' . rawurlencode($userId) . '/' . rawurlencode($apiKey) . '/subscriber.saveSubscribe.json';
        $data = [
            'list_id' => $listId,
            'email' => $email,
            'firstname' => self::value($row, 'first_name'),
            'lastname' => self::value($row, 'last_name'),
            'ip' => isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '127.0.0.1',
            'props' => $props,
        ];

        $response = self::post($url, $data);
        if ($response['ok']) {
            return ['ok' => true, 'message' => $response['body']];
        }

        return ['ok' => false, 'message' => $response['body']];
    }

    private static function post($url, $data)
    {
        $body = http_build_query($data);
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
                'content' => $body,
                'timeout' => 15,
            ],
        ]);

        $response = @file_get_contents($url, false, $context);
        if ($response !== false) {
            return ['ok' => true, 'body' => $response];
        } else {
            return ['ok' => false, 'body' => 'HTTP request failed'];
        }
    }

    private static function query($sql)
    {
        try {
            if (class_exists('Yii')) {
                if (isset(\Yii::$app)) {
                    if (isset(\Yii::$app->db)) {
                        return \Yii::$app->db->createCommand($sql)->queryAll();
                    }
                }
            }

            if (function_exists('tep_db_query')) {
                $tepDbReady = function_exists('tep_db_fetch_array');
            } else {
                $tepDbReady = false;
            }

            if ($tepDbReady) {
                $result = tep_db_query($sql);
                $rows = [];
                while ($row = tep_db_fetch_array($result)) {
                    $rows[] = $row;
                }
                return $rows;
            }
            return [['error' => 'No database connection available.']];
        } catch (\Throwable $e) {
            return [['error' => $e->getMessage()]];
        }
    }

    private static function syncResultHtml($result)
    {
        $html = '<p>' . self::h($result['message']) . '</p>' .
            '<p>Sent: ' . (int) $result['sent'] . '</p>';
        if (!empty($result['errors'])) {
            $html .= '<pre>' . self::h(implode("\n", $result['errors'])) . '</pre>';
        }
        return $html;
    }

    private static function cfg($key, $default = '')
    {
        $value = '';
        if (method_exists(__CLASS__, 'getCfgValue')) {
            $value = (string) self::getCfgValue($key);
        }
        if ($value === '') {
            if (defined($key)) {
                $value = (string) constant($key);
            }
        }
        return $value !== '' ? trim($value) : $default;
    }

    private static function currency()
    {
        return defined('DEFAULT_CURRENCY') ? DEFAULT_CURRENCY : 'GBP';
    }

    private static function shopPath()
    {
        return trim(self::cfg('NEWSMAN_SHOP_PATH', 'printshop'), '/');
    }

    private static function shopUrl($path = '')
    {
        return self::siteUrl('/' . self::shopPath() . '/' . ltrim($path, '/'));
    }

    private static function imageUrl($image)
    {
        if ($image === '') {
            return self::shopUrl('/themes/printshop/img/logo-2.png');
        }
        if (preg_match('~^https?://~i', $image)) {
            return $image;
        }
        return self::shopUrl('/images/' . ltrim($image, '/'));
    }

    private static function siteUrl($path = '')
    {
        if (class_exists('Yii')) {
            if (isset(\Yii::$app)) {
                if (isset(\Yii::$app->request)) {
                    if (\Yii::$app->request->isSecureConnection) {
                        $scheme = 'https';
                    } else {
                        $scheme = 'http';
                    }
                }
            }
        }

        if (!isset($scheme)) {
            if (!empty($_SERVER['HTTPS'])) {
                if ($_SERVER['HTTPS'] !== 'off') {
                    $scheme = 'https';
                } else {
                    $scheme = 'http';
                }
            } else {
                $scheme = 'http';
            }
        }

        return $scheme . '://' . self::siteHost() . '/' . ltrim($path, '/');
    }

    private static function siteHost()
    {
        if (class_exists('Yii')) {
            if (isset(\Yii::$app)) {
                if (isset(\Yii::$app->request)) {
                    if (isset(\Yii::$app->request->hostName)) {
                        return \Yii::$app->request->hostName;
                    }
                }
            }
        }

        if (isset($_SERVER['HTTP_HOST'])) {
            return $_SERVER['HTTP_HOST'];
        } elseif (isset($_SERVER['SERVER_NAME'])) {
            return $_SERVER['SERVER_NAME'];
        } else {
            return 'localhost';
        }
    }

    private static function h($value)
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }

}
