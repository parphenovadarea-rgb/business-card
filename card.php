<?php
declare(strict_types=1);

/*
 * Визитная карточка 600x300 на GD — дизайн «Розовая вуаль».
 * Белый фон, розовые разводы «алкогольных чернил», золотая прожилка с крапинками,
 * тонкие коричнево-золотые линии.
 * Параметры (необязательные): name, group, phone, email, city.
 * save=1 — дополнительно сохранить файл card.png.
 *
 * Важно: до header() нет никакого вывода (ни текста, ни пробелов, ни закрывающего ?>).
 */

const WIDTH  = 600;
const HEIGHT = 300;
const FONT_REGULAR = __DIR__ . '/fonts/DejaVuSans.ttf';
const FONT_BOLD    = __DIR__ . '/fonts/DejaVuSans-Bold.ttf';
const OUTPUT_FILE  = __DIR__ . '/card.png';

// Данные по умолчанию — замените на свои.
const DEFAULT_NAME    = 'Иванов Иван Иванович';
const DEFAULT_GROUP   = 'ИСП 34';
const DEFAULT_PHONE   = '+7 (900) 000-00-00';
const DEFAULT_EMAIL   = 'student@example.com';
const DEFAULT_CITY    = 'Новосибирск';
const COLLEGE         = 'Новосибирский электромеханический колледж';

const FRAME_OUTER = 18;   // основная рамка: отступ 18 px
const FRAME_INNER = 26;   // внутренняя тонкая рамка: отступ 26 px
const CENTER_X    = 300;  // ось симметрии карточки
const SEED        = 2026; // фиксированное зерно: узор одинаковый при каждой генерации

// ---------- вспомогательные функции ----------

function fail(int $status, string $text): never
{
    http_response_code($status);
    header('Content-Type: text/plain; charset=utf-8');
    echo $text;
    exit;
}

function param(string $key, string $default, int $max): string
{
    $value = $_GET[$key] ?? '';
    if (!is_string($value)) {
        return $default;
    }
    $value = trim((string) preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $value));
    return $value === '' ? $default : mb_substr($value, 0, $max);
}

/** Выделяет цвет; alpha: 0 — непрозрачный, 127 — полностью прозрачный. */
function color(GdImage $img, int $r, int $g, int $b, int $alpha = 0): int
{
    return (int) imagecolorallocatealpha($img, $r, $g, $b, $alpha);
}

function textWidth(int $size, string $font, string $text): int
{
    $box = imagettfbbox($size, 0, $font, $text);
    return $box === false ? 0 : $box[2] - $box[0];
}

/** Уменьшает размер шрифта, пока текст не поместится в maxWidth. */
function fitSize(string $text, string $font, int $size, int $maxWidth): int
{
    while ($size > 8 && textWidth($size, $font, $text) > $maxWidth) {
        $size--;
    }
    return $size;
}

/** Текст с тенью: сначала смещённая полупрозрачная копия, потом основной цвет. */
function shadowText(GdImage $img, int $size, int $x, int $y, int $color, int $shadow, string $font, string $text, int $offset = 2): void
{
    imagettftext($img, $size, 0, $x + $offset, $y + $offset, $shadow, $font, $text);
    imagettftext($img, $size, 0, $x, $y, $color, $font, $text);
}

/** Текст, отцентрованный по горизонтали относительно cx (y — базовая линия). */
function centerText(GdImage $img, int $size, int $cx, int $y, int $color, ?int $shadow, string $font, string $text): void
{
    $x = (int) round($cx - textWidth($size, $font, $text) / 2);
    if ($shadow !== null) {
        shadowText($img, $size, $x, $y, $color, $shadow, $font, $text, 1);
    } else {
        imagettftext($img, $size, 0, $x, $y, $color, $font, $text);
    }
}

/**
 * Скруглённый прямоугольник для полупрозрачного цвета (imagecolorallocatealpha).
 * Части не перекрываются, иначе в местах наложения альфа сложится дважды и появятся полосы.
 * Углы — четверти круга через imagefilledarc.
 */
function alphaRoundRect(GdImage $img, int $x1, int $y1, int $x2, int $y2, int $r, int $color): void
{
    imagefilledrectangle($img, $x1, $y1 + $r + 1, $x2, $y2 - $r - 1, $color);          // середина
    imagefilledrectangle($img, $x1 + $r + 1, $y1, $x2 - $r - 1, $y1 + $r, $color);      // верхняя полоса
    imagefilledrectangle($img, $x1 + $r + 1, $y2 - $r, $x2 - $r - 1, $y2, $color);      // нижняя полоса
    $d = $r * 2;
    imagefilledarc($img, $x1 + $r, $y1 + $r, $d, $d, 180, 270, $color, IMG_ARC_PIE);  // левый верхний
    imagefilledarc($img, $x2 - $r, $y1 + $r, $d, $d, 270, 360, $color, IMG_ARC_PIE);  // правый верхний
    imagefilledarc($img, $x2 - $r, $y2 - $r, $d, $d, 0, 90, $color, IMG_ARC_PIE);     // правый нижний
    imagefilledarc($img, $x1 + $r, $y2 - $r, $d, $d, 90, 180, $color, IMG_ARC_PIE);   // левый нижний
}

/**
 * «Вуаль»: много наложенных полупрозрачных эллипсов разных оттенков розового.
 * $yOf($x) возвращает вертикальное положение центра пятна для данного x.
 */
function inkBlobs(GdImage $img, array $palette, int $edge, int $count, int $x1, int $x2, int $minW, int $maxW, callable $yOf): void
{
    for ($i = 0; $i < $count; $i++) {
        $x = mt_rand($x1, $x2);
        $y = (int) $yOf($x);
        $w = mt_rand($minW, $maxW);
        $h = (int) round($w * mt_rand(40, 75) / 100);
        imagefilledellipse($img, $x, $y, $w, $h, $palette[mt_rand(0, count($palette) - 1)]);
        if (mt_rand(0, 5) === 0) {
            imageellipse($img, $x, $y, $w, $h, $edge);   // тонкий «край чернил»
        }
    }
}

/** Золотая прожилка вдоль кривой $wave и россыпь золотых крапинок вокруг неё. */
function goldVein(GdImage $img, callable $wave, int $x1, int $x2, int $gold, int $light, array $dust, int $specks): void
{
    $prevY = (int) round($wave($x1));
    for ($x = $x1 + 2; $x <= $x2; $x += 2) {
        $y = (int) round($wave($x));
        imagesetthickness($img, ($x % 70) < 35 ? 3 : 2);   // неровная толщина, как у мазка
        imageline($img, $x - 2, $prevY, $x, $y, $gold);
        imagesetthickness($img, 1);
        imageline($img, $x - 2, $prevY - 1, $x, $y - 1, $light);  // блик
        $prevY = $y;
    }
    imagesetthickness($img, 1);

    for ($i = 0; $i < $specks; $i++) {
        $x = mt_rand($x1, $x2);
        $d = (mt_rand(-100, 100) + mt_rand(-100, 100)) / 200 * 24;   // гуще у самой линии
        $y = (int) round($wave($x) + $d);
        $c = $dust[mt_rand(0, count($dust) - 1)];
        $s = mt_rand(0, 9) < 7 ? 1 : 2;
        if ($s === 1) {
            imagesetpixel($img, $x, $y, $c);
        } else {
            imagefilledellipse($img, $x, $y, $s + 1, $s + 1, $c);
        }
    }
}

// ---------- проверки окружения ----------

if (!extension_loaded('gd') || !function_exists('imagettftext')) {
    fail(500, 'Расширение GD (с поддержкой FreeType) не включено. Проверьте: php -m | grep gd');
}
foreach ([FONT_REGULAR, FONT_BOLD] as $font) {
    if (!is_readable($font)) {
        fail(500, 'Не найден файл шрифта: ' . basename($font) . ' (папка fonts рядом со скриптом).');
    }
}

$name  = param('name', DEFAULT_NAME, 40);
$group = param('group', DEFAULT_GROUP, 20);
$phone = param('phone', DEFAULT_PHONE, 24);
$email = param('email', DEFAULT_EMAIL, 40);
$city  = param('city', DEFAULT_CITY, 30);

mt_srand(SEED);

// ---------- 1. Холст и вертикальный градиент (цикл по строкам) ----------

$img = imagecreatetruecolor(WIDTH, HEIGHT);
if ($img === false) {
    fail(500, 'Не удалось создать изображение.');
}
imagealphablending($img, true);
imagesavealpha($img, true);

$from = [255, 255, 255];   // белый сверху
$to   = [248, 222, 230];   // нежно-розовый снизу
for ($y = 0; $y < HEIGHT; $y++) {
    $t = $y / (HEIGHT - 1);
    $row = imagecolorallocate(
        $img,
        (int) round($from[0] + ($to[0] - $from[0]) * $t),
        (int) round($from[1] + ($to[1] - $from[1]) * $t),
        (int) round($from[2] + ($to[2] - $from[2]) * $t)
    );
    imagefilledrectangle($img, 0, $y, WIDTH - 1, $y, $row);
}

// Палитра: основные цвета + полупрозрачные оттенки.
$wine      = color($img, 122, 52, 76);    // ФИО
$ink       = color($img, 74, 52, 60);     // основной текст
$gray      = color($img, 130, 108, 112);  // колледж, подписи
$brown     = color($img, 112, 90, 56);    // тонкие линии
$gold      = color($img, 212, 168, 70);
$goldLight = color($img, 250, 226, 150);
$roseGold  = color($img, 196, 150, 112);  // основная рамка
$pinkLine  = color($img, 236, 184, 194);  // внутренняя рамка
$textShadow = color($img, 60, 60, 70, 85);       // тень текста: серая полупрозрачная
$plateColor = color($img, 255, 255, 255, 30);    // акцент: белый, полупрозрачный
$plateShadow = color($img, 120, 60, 80, 100);    // тень акцента: смещённая, полупрозрачная
$dots = [                                         // узор из точек: 5 цветов с полупрозрачностью
    color($img, 212, 168, 70, 30),
    color($img, 232, 150, 165, 30),
    color($img, 122, 52, 76, 60),
    color($img, 170, 150, 210, 40),
    color($img, 130, 190, 180, 40),
];
$veil = [
    color($img, 244, 194, 194, 112),
    color($img, 240, 170, 182, 116),
    color($img, 248, 214, 206, 108),
    color($img, 232, 150, 165, 118),
    color($img, 250, 226, 220, 104),
];
$veilEdge = color($img, 224, 140, 156, 114);
$goldDust = [color($img, 212, 168, 70, 20), color($img, 232, 192, 96, 55), color($img, 186, 146, 60, 10)];

// ---------- 2. Рамки ----------

// Основная рамка (2 px) с отступом 18 и внутренняя тонкая (1 px) с отступом 26.
imagerectangle($img, FRAME_OUTER, FRAME_OUTER, WIDTH - 1 - FRAME_OUTER, HEIGHT - 1 - FRAME_OUTER, $roseGold);
imagerectangle($img, FRAME_OUTER + 1, FRAME_OUTER + 1, WIDTH - 2 - FRAME_OUTER, HEIGHT - 2 - FRAME_OUTER, $roseGold);
imagerectangle($img, FRAME_INNER, FRAME_INNER, WIDTH - 1 - FRAME_INNER, HEIGHT - 1 - FRAME_INNER, $pinkLine);

// ---------- 5. Узор «розовая вуаль» (5 оттенков + золото), обрезан по внутренней рамке ----------

imagesetclip($img, FRAME_INNER + 1, FRAME_INNER + 1, WIDTH - 2 - FRAME_INNER, HEIGHT - 2 - FRAME_INNER);

// Верхняя вуаль: пятна от верхнего края до волнистой границы.
$topWave = fn (float $x): float => 74 + 22 * sin($x / 95 + 0.6) + 8 * sin($x / 37);
inkBlobs($img, $veil, $veilEdge, 170, 10, 590, 50, 150, fn (int $x): int => mt_rand(10, (int) $topWave($x) + 15));
goldVein($img, $topWave, FRAME_INNER + 1, WIDTH - 2 - FRAME_INNER, $gold, $goldLight, $goldDust, 220);

// Нижняя вуаль в правом нижнем углу — как оборотная сторона образца.
$bottomWave = fn (float $x): float => 300 - ($x - 400) * 0.2 + 5 * sin($x / 23);
inkBlobs($img, $veil, $veilEdge, 55, 400, 600, 40, 110, fn (int $x): int => mt_rand((int) $bottomWave($x) - 5, 300));
goldVein($img, $bottomWave, 400, WIDTH - 2 - FRAME_INNER, $gold, $goldLight, $goldDust, 60);

// Геометрический узор в левом нижнем углу: сетка точек, цвета чередуются по диагонали.
for ($gy = 0; $gy < 2; $gy++) {
    for ($gx = 0; $gx < 27; $gx++) {
        imagefilledellipse($img, 36 + $gx * 9 + $gy * 4, 260 + $gy * 7, 4, 4, $dots[($gx + $gy) % count($dots)]);
    }
}

imagesetclip($img, 0, 0, WIDTH - 1, HEIGHT - 1);

// ---------- 3. Акцент: полупрозрачная скруглённая плашка с тенью ----------

// imagealphablending включён выше, imagesavealpha — для сохранения альфы в PNG.
$px1 = 70; $py1 = 112; $px2 = 530; $py2 = 206;
alphaRoundRect($img, $px1 + 5, $py1 + 6, $px2 + 5, $py2 + 6, 20, $plateShadow);  // тень: смещена на (5, 6)
alphaRoundRect($img, $px1, $py1, $px2, $py2, 20, $plateColor);                   // сам акцент

// ---------- 4. Текст (выровнен относительно акцентной плашки) ----------

$plateCx = intdiv($px1 + $px2, 2);            // центр плашки по горизонтали
$textMaxWidth = $px2 - $px1 - 40;             // поля по 20 px внутри плашки
$nameSize = fitSize($name, FONT_BOLD, 24, $textMaxWidth);
$baseName = $py1 + 34;                        // базовая линия ФИО от верха плашки
centerText($img, $nameSize, $plateCx, $baseName, $wine, $textShadow, FONT_BOLD, $name);

// Линии под заголовком: длинная коричневая с круглыми «колпачками» (imagefilledarc) и короткая золотая.
imagefilledrectangle($img, 110, $baseName + 12, 490, $baseName + 13, $brown);
imagefilledarc($img, 110, $baseName + 12, 7, 7, 0, 360, $gold, IMG_ARC_PIE);
imagefilledarc($img, 490, $baseName + 12, 7, 7, 0, 360, $gold, IMG_ARC_PIE);
imagefilledrectangle($img, 230, $baseName + 18, 370, $baseName + 18, $gold);

$groupText = 'Студент группы ' . $group . '  ·  ' . $city;
centerText($img, fitSize($groupText, FONT_REGULAR, 13, $textMaxWidth), $plateCx, $baseName + 40, $ink, $textShadow, FONT_REGULAR, $groupText);
centerText($img, fitSize(COLLEGE, FONT_REGULAR, 10, $textMaxWidth), $plateCx, $baseName + 55, $gray, null, FONT_REGULAR, COLLEGE);

// Контакты: e-mail слева, телефон справа, между ними вертикальный разделитель.
imagettftext($img, 13, 0, 56, 244, $gold, FONT_REGULAR, '✉');
imagettftext($img, fitSize($email, FONT_BOLD, 11, 196), 0, 80, 243, $ink, FONT_BOLD, $email);
imagefilledrectangle($img, 52, 252, 282, 252, $brown);

imagefilledrectangle($img, CENTER_X, 222, CENTER_X, 262, $brown);

imagettftext($img, 14, 0, 322, 245, $gold, FONT_REGULAR, '☎');
imagettftext($img, fitSize($phone, FONT_BOLD, 12, 196), 0, 350, 243, $ink, FONT_BOLD, $phone);
imagefilledrectangle($img, 318, 252, 548, 252, $brown);

// ---------- 6. Вывод и сохранение ----------

if (($_GET['save'] ?? '') === '1') {
    if (!is_writable(dirname(OUTPUT_FILE)) || !imagepng($img, OUTPUT_FILE)) {
        imagedestroy($img);
        fail(500, 'Не удалось сохранить card.png: проверьте права на запись в папке проекта.');
    }
}

header('Content-Type: image/png');
header('Cache-Control: no-store');
imagepng($img);
imagedestroy($img);
