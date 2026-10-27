<?php
/**
 * Claude Connector by Piskee
 *
 * @author    Piskee <louisalexandre@piskee.com>
 * @copyright 2026 Piskee
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License v3.0 (AFL-3.0)
 */
declare(strict_types=1);

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Product images: adding them, their alt text, the cover, and reshaping the
 * original (downsizing, square canvas) before thumbnails are rebuilt.
 */
class PiskeeConnectorImages
{
    private const MAX_DOWNLOAD_BYTES = 15728640;

    /**
     * @param array<string, mixed> $args
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    public function listProductImages(array $args, array $context): array
    {
        $c = PiskeeConnectorHelper::boot($context);
        $idProduct = (int) ($args['id_product'] ?? 0);
        $product = new Product($idProduct, false, $c['id_lang'], $c['id_shop']);
        if (!PiskeeConnectorHelper::inShop($product, $c['id_shop'])) {
            return PiskeeConnectorHelper::error('Product ' . $idProduct . ' not found in this shop.');
        }

        $languages = PiskeeConnectorHelper::languages($c['id_shop']);
        $link = Context::getContext()->link;
        $out = [];
        foreach (Image::getImages($c['id_lang'], $idProduct, null, $c['id_shop']) as $row) {
            $image = new Image((int) $row['id_image']);
            $original = self::originalPath($image);
            $size = is_file($original) ? @getimagesize($original) : false;
            $legends = [];
            foreach ($languages as $iso => $idLang) {
                $legends[$iso] = (string) ($image->legend[$idLang] ?? '');
            }
            $out[] = [
                'id_image' => (int) $image->id,
                'position' => (int) $image->position,
                'cover' => (bool) $image->cover,
                'alt_text' => $legends,
                'original' => $size ? ['width' => $size[0], 'height' => $size[1], 'bytes' => (int) filesize($original)] : null,
                'url' => $link->getImageLink((string) $product->link_rewrite ?: 'product', (string) (int) $image->id),
            ];
        }

        return PiskeeConnectorHelper::json(['id_product' => $idProduct, 'images' => $out]);
    }

    /**
     * @param array<string, mixed> $args
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    public function addProductImageFromUrl(array $args, array $context): array
    {
        $c = PiskeeConnectorHelper::boot($context);
        $idProduct = (int) ($args['id_product'] ?? 0);
        if (!PiskeeConnectorHelper::inShop(new Product($idProduct, false, null, $c['id_shop']), $c['id_shop'])) {
            return PiskeeConnectorHelper::error('Product ' . $idProduct . ' not found in this shop.');
        }

        $translations = PiskeeConnectorHelper::translations($args['alt_text'] ?? null, $c['id_shop']);
        if (!$translations['ok']) {
            return PiskeeConnectorHelper::error($translations['error']);
        }

        $download = $this->download((string) ($args['url'] ?? ''));
        if ($download['error'] !== '') {
            return PiskeeConnectorHelper::error($download['error']);
        }
        $tmp = $download['path'];

        $image = new Image();
        $image->id_product = $idProduct;
        $image->position = Image::getHighestPosition($idProduct) + 1;
        $makeCover = (bool) ($args['cover'] ?? false) || !Image::getCover($idProduct);
        $image->cover = $makeCover ? true : null;
        foreach (PiskeeConnectorHelper::languages($c['id_shop']) as $idLang) {
            $image->legend[$idLang] = Tools::substr((string) ($translations['map'][$idLang]['value'] ?? ''), 0, 128);
        }

        $error = PiskeeConnectorHelper::validate($image);
        if ($error !== '') {
            @unlink($tmp);

            return PiskeeConnectorHelper::error('Not saved: ' . $error);
        }
        if ($makeCover) {
            Image::deleteCover($idProduct);
        }
        if (!$image->add()) {
            @unlink($tmp);

            return PiskeeConnectorHelper::error('PrestaShop refused to create the image.');
        }
        $image->associateTo([$c['id_shop']]);

        $path = $image->getPathForCreation();
        if (!ImageManager::resize($tmp, $path . '.jpg')) {
            $image->delete();
            @unlink($tmp);

            return PiskeeConnectorHelper::error('The file could not be read as an image.');
        }
        $this->buildThumbnails($path . '.jpg', $path);
        @unlink($tmp);
        Hook::exec('actionWatermark', ['id_image' => (int) $image->id, 'id_product' => $idProduct]);

        return PiskeeConnectorHelper::json(['id_image' => (int) $image->id, 'id_product' => $idProduct, 'cover' => $makeCover, 'created' => true]);
    }

    /**
     * Alt text and cover.
     *
     * @param array<string, mixed> $args
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    public function updateProductImage(array $args, array $context): array
    {
        $c = PiskeeConnectorHelper::boot($context);
        $idImage = (int) ($args['id_image'] ?? 0);
        $image = new Image($idImage);
        if (!PiskeeConnectorHelper::inShop($image, $c['id_shop']) || !PiskeeConnectorHelper::inShop(new Product((int) $image->id_product, false, null, $c['id_shop']), $c['id_shop'])) {
            return PiskeeConnectorHelper::error('Image ' . $idImage . ' not found in this shop.');
        }

        $translations = PiskeeConnectorHelper::translations($args['alt_text'] ?? null, $c['id_shop']);
        if (!$translations['ok']) {
            return PiskeeConnectorHelper::error($translations['error']);
        }

        $changes = [];
        foreach ($translations['map'] as $idLang => $fields) {
            $value = Tools::substr(trim((string) ($fields['value'] ?? '')), 0, 128);
            if ((string) ($image->legend[$idLang] ?? '') !== $value) {
                $changes['alt_text.' . PiskeeConnectorHelper::iso($idLang)] = ['from' => (string) ($image->legend[$idLang] ?? ''), 'to' => $value];
                $image->legend[$idLang] = $value;
            }
        }
        $makeCover = !empty($args['cover']) && !$image->cover;
        if ($makeCover) {
            $image->cover = true;
            $changes['cover'] = ['from' => false, 'to' => true];
        }

        if ($changes === []) {
            return PiskeeConnectorHelper::json(['id_image' => $idImage, 'updated' => false, 'message' => 'Nothing to change.']);
        }
        $error = PiskeeConnectorHelper::validate($image);
        if ($error !== '') {
            return PiskeeConnectorHelper::error('Not saved: ' . $error);
        }
        if ($makeCover) {
            Image::deleteCover((int) $image->id_product);
        }
        if (!$image->update()) {
            return PiskeeConnectorHelper::error('Not saved: PrestaShop refused the image.');
        }

        return PiskeeConnectorHelper::json(['id_image' => $idImage, 'id_product' => (int) $image->id_product, 'updated' => true, 'changes' => $changes]);
    }

    /**
     * Reshapes the originals of a product (or one image), then rebuilds every
     * thumbnail from them.
     *
     * @param array<string, mixed> $args
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    public function optimizeProductImages(array $args, array $context): array
    {
        $c = PiskeeConnectorHelper::boot($context);
        $idProduct = (int) ($args['id_product'] ?? 0);
        if (!PiskeeConnectorHelper::inShop(new Product($idProduct, false, null, $c['id_shop']), $c['id_shop'])) {
            return PiskeeConnectorHelper::error('Product ' . $idProduct . ' not found in this shop.');
        }

        $maxSide = (int) ($args['max_side'] ?? 0);
        if ($maxSide !== 0 && ($maxSide < 300 || $maxSide > 6000)) {
            return PiskeeConnectorHelper::error('max_side must be between 300 and 6000 pixels, or left out.');
        }
        $square = (bool) ($args['square'] ?? false);
        $background = (string) ($args['background'] ?? 'ffffff');
        if (!preg_match('/^[0-9a-fA-F]{6}$/', $background)) {
            return PiskeeConnectorHelper::error('background must be a hex colour such as ffffff.');
        }
        $onlyImage = (int) ($args['id_image'] ?? 0);

        if (!function_exists('imagecreatetruecolor')) {
            return PiskeeConnectorHelper::error('The GD extension of PHP is missing on this server: images cannot be reshaped.');
        }

        $report = [];
        foreach (Image::getImages($c['id_lang'], $idProduct, null, $c['id_shop']) as $row) {
            $image = new Image((int) $row['id_image']);
            if ($onlyImage > 0 && (int) $image->id !== $onlyImage) {
                continue;
            }
            $original = self::originalPath($image);
            $base = substr($original, 0, -strlen('.' . $image->image_format));
            if (!is_file($original)) {
                $report[] = ['id_image' => (int) $image->id, 'status' => 'original file missing'];
                continue;
            }

            $before = @getimagesize($original);
            $bytesBefore = (int) filesize($original);
            $kept = $this->keepOriginal($original, (int) $image->id);
            $reshaped = $this->reshape($original, $maxSide, $square, $background);
            if ($reshaped['error'] !== '') {
                $report[] = ['id_image' => (int) $image->id, 'status' => $reshaped['error']];
                continue;
            }
            $this->buildThumbnails($original, $base);
            $after = @getimagesize($original);
            clearstatcache(true, $original);

            $report[] = [
                'id_image' => (int) $image->id,
                'status' => $reshaped['changed'] ? 'reshaped and thumbnails rebuilt' : 'thumbnails rebuilt',
                'before' => $before ? $before[0] . 'x' . $before[1] . ' (' . round($bytesBefore / 1024) . ' KB)' : null,
                'after' => $after ? $after[0] . 'x' . $after[1] . ' (' . round(filesize($original) / 1024) . ' KB)' : null,
                'original_kept' => $reshaped['changed'] ? $kept : null,
            ];
        }

        if ($report === []) {
            return PiskeeConnectorHelper::error($onlyImage > 0 ? 'Image ' . $onlyImage . ' does not belong to product ' . $idProduct . '.' : 'This product has no image.');
        }

        return PiskeeConnectorHelper::json(['id_product' => $idProduct, 'images' => $report]);
    }

    /**
     * Downsizes past max_side and/or centres on a square canvas, in place.
     *
     * @return array{changed:bool, error:string}
     */
    private function reshape(string $file, int $maxSide, bool $square, string $background): array
    {
        $info = @getimagesize($file);
        if (!$info) {
            return ['changed' => false, 'error' => 'not a readable image'];
        }
        [$width, $height] = $info;
        $scale = ($maxSide > 0 && max($width, $height) > $maxSide) ? $maxSide / max($width, $height) : 1.0;
        $newW = (int) round($width * $scale);
        $newH = (int) round($height * $scale);
        $needsSquare = $square && $newW !== $newH;

        if ($scale === 1.0 && !$needsSquare) {
            return ['changed' => false, 'error' => ''];
        }

        $source = @imagecreatefromstring((string) file_get_contents($file));
        if (!$source) {
            return ['changed' => false, 'error' => 'format not supported by GD'];
        }
        // Camera photos are often stored sideways with an EXIF orientation
        // flag: apply it, since the rewritten JPEG has no EXIF any more.
        if (($info[2] ?? 0) === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
            $exif = @exif_read_data($file);
            $rotation = [3 => 180, 6 => -90, 8 => 90][(int) ($exif['Orientation'] ?? 1)] ?? 0;
            if ($rotation !== 0) {
                $rotated = imagerotate($source, $rotation, 0);
                if ($rotated !== false) {
                    imagedestroy($source);
                    $source = $rotated;
                    if ($rotation !== 180) {
                        [$width, $height] = [$height, $width];
                        $scale = ($maxSide > 0 && max($width, $height) > $maxSide) ? $maxSide / max($width, $height) : 1.0;
                        $newW = (int) round($width * $scale);
                        $newH = (int) round($height * $scale);
                        $needsSquare = $square && $newW !== $newH;
                    }
                }
            }
        }

        $canvasW = $needsSquare ? max($newW, $newH) : $newW;
        $canvasH = $needsSquare ? max($newW, $newH) : $newH;
        $canvas = imagecreatetruecolor($canvasW, $canvasH);
        $rgb = array_map('hexdec', str_split($background, 2));
        imagefill($canvas, 0, 0, (int) imagecolorallocate($canvas, $rgb[0], $rgb[1], $rgb[2]));
        imagecopyresampled($canvas, $source, (int) (($canvasW - $newW) / 2), (int) (($canvasH - $newH) / 2), 0, 0, $newW, $newH, $width, $height);

        $ok = imagejpeg($canvas, $file, 90);
        imagedestroy($source);
        imagedestroy($canvas);

        return $ok ? ['changed' => true, 'error' => ''] : ['changed' => false, 'error' => 'could not write the image'];
    }

    /**
     * Path of the original file of a product image (PrestaShop 8 and 9, new
     * or legacy image folders).
     */
    private static function originalPath(Image $image): string
    {
        $relative = method_exists($image, 'getExistingImgPath') ? (string) $image->getExistingImgPath() : (string) $image->getImgPath();

        return _PS_PRODUCT_IMG_DIR_ . $relative . '.' . ($image->image_format ?: 'jpg');
    }

    /**
     * Copies an original before it is reshaped, so it can be recovered.
     */
    private function keepOriginal(string $original, int $idImage): ?string
    {
        $dir = _PS_ROOT_DIR_ . '/var/piskeeconnector/images/';
        if (!is_dir($dir)) {
            @mkdir($dir, 0750, true);
            @file_put_contents($dir . 'index.php', "<?php\nheader('HTTP/1.1 403 Forbidden');\nexit;\n");
            @file_put_contents(dirname($dir) . '/.htaccess', "Require all denied\nDeny from all\n");
        }
        $name = $idImage . '-' . date('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.' . pathinfo($original, PATHINFO_EXTENSION);

        return @copy($original, $dir . $name) ? 'var/piskeeconnector/images/' . $name : null;
    }

    /**
     * Every product thumbnail, in every format the shop generates.
     */
    private function buildThumbnails(string $source, string $base): void
    {
        $formats = ['jpg'];
        try {
            if (class_exists('\PrestaShop\PrestaShop\Core\Image\ImageFormatConfiguration')
                && class_exists('\PrestaShop\PrestaShop\Adapter\Configuration')) {
                $configuration = new \PrestaShop\PrestaShop\Core\Image\ImageFormatConfiguration(new \PrestaShop\PrestaShop\Adapter\Configuration());
                $formats = $configuration->getGenerationFormats();
            }
        } catch (Throwable $e) {
            $formats = ['jpg'];
        }

        foreach (ImageType::getImagesTypes('products') as $type) {
            foreach ($formats as $format) {
                ImageManager::resize(
                    $source,
                    $base . '-' . stripslashes((string) $type['name']) . '.' . $format,
                    (int) $type['width'],
                    (int) $type['height'],
                    $format
                );
            }
        }
    }

    /**
     * Fetches a public image, refusing private networks and oversized files.
     *
     * @return array{path:string, error:string}
     */
    private function download(string $url): array
    {
        $parts = parse_url($url);
        $scheme = is_array($parts) ? Tools::strtolower((string) ($parts['scheme'] ?? '')) : '';
        if (!is_array($parts) || !in_array($scheme, ['http', 'https'], true) || empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])) {
            return ['path' => '', 'error' => 'url must be a public http or https address.'];
        }
        $port = (int) ($parts['port'] ?? ($scheme === 'https' ? 443 : 80));
        if (!in_array($port, [80, 443], true)) {
            return ['path' => '', 'error' => 'Only images on the standard web ports (80, 443) can be downloaded.'];
        }
        $addresses = @gethostbynamel((string) $parts['host']) ?: [];
        if ($addresses === []) {
            return ['path' => '', 'error' => 'The host of this url cannot be resolved.'];
        }
        foreach ($addresses as $address) {
            if (!self::publicAddress($address)) {
                return ['path' => '', 'error' => 'This url points to a private network and is refused.'];
            }
        }
        if (!function_exists('curl_init')) {
            return ['path' => '', 'error' => 'The cURL extension of PHP is missing on this server.'];
        }

        $tmp = tempnam(_PS_CACHE_DIR_, 'pkc');
        $handle = $tmp !== false ? fopen($tmp, 'wb') : false;
        if ($tmp === false || $handle === false) {
            return ['path' => '', 'error' => 'Cannot write a temporary file.'];
        }
        $received = 0;
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_PROXY => '',
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_USERAGENT => 'PiskeeForPrestaShop/2.0',
            CURLOPT_RESOLVE => [$parts['host'] . ':' . $port . ':' . $addresses[0]],
            CURLOPT_WRITEFUNCTION => static function ($ch, $chunk) use ($handle, &$received) {
                $received += strlen($chunk);
                if ($received > self::MAX_DOWNLOAD_BYTES) {
                    return 0;
                }

                return (int) fwrite($handle, $chunk);
            },
        ]);
        curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        fclose($handle);

        if ($status !== 200 || $received === 0) {
            @unlink($tmp);

            return ['path' => '', 'error' => $received > self::MAX_DOWNLOAD_BYTES ? 'The image is larger than 15 MB.' : 'The image could not be downloaded (HTTP ' . $status . ').'];
        }
        $info = @getimagesize($tmp);
        $type = $info ? $info[2] : false;
        if (!in_array($type, [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF, IMAGETYPE_WEBP], true)) {
            @unlink($tmp);

            return ['path' => '', 'error' => 'The file is not a JPEG, PNG, GIF or WebP image.'];
        }

        return ['path' => $tmp, 'error' => ''];
    }

    /**
     * Public internet addresses only: no private, loopback, link-local,
     * shared (100.64/10), benchmark (198.18/15) or reserved ranges.
     */
    private static function publicAddress(string $address): bool
    {
        if (!filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return false;
        }
        $long = ip2long($address);
        foreach ([['100.64.0.0', 10], ['198.18.0.0', 15], ['192.0.0.0', 24], ['192.0.2.0', 24], ['198.51.100.0', 24], ['203.0.113.0', 24], ['224.0.0.0', 4], ['240.0.0.0', 4], ['0.0.0.0', 8], ['127.0.0.0', 8], ['169.254.0.0', 16]] as [$net, $bits]) {
            $mask = -1 << (32 - $bits);
            if (($long & $mask) === (ip2long($net) & $mask)) {
                return false;
            }
        }

        return true;
    }
}
