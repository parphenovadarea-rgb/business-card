<?php
declare(strict_types=1);

function e(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function get(string $key): string
{
    return is_string($_GET[$key] ?? null) ? trim($_GET[$key]) : '';
}

$fields = ['name' => 'ФИО', 'group' => 'Группа', 'phone' => 'Телефон', 'email' => 'E-mail', 'city' => 'Город'];
$placeholders = ['name' => 'Иванов Иван Иванович', 'group' => 'ИСП 34', 'phone' => '+7 (900) 000-00-00', 'email' => 'student@example.com', 'city' => 'Новосибирск'];

// При первом открытии страницы файл card.png сохраняется; после отправки формы — по галочке.
$submitted = isset($_GET['go']);
$save = $submitted ? get('save') === '1' : true;

$params = [];
foreach (array_keys($fields) as $key) {
    if (get($key) !== '') {
        $params[$key] = get($key);
    }
}
if ($save) {
    $params['save'] = '1';
}
$src = 'card.php' . ($params !== [] ? '?' . http_build_query($params) : '');
$gd = extension_loaded('gd') && function_exists('imagettftext');
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Визитная карточка</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <main>
        <h1>Визитная карточка (GD)</h1>

        <form method="get">
            <input type="hidden" name="go" value="1">
            <?php foreach ($fields as $key => $title): ?>
                <label><?= e($title) ?>
                    <input name="<?= e($key) ?>" maxlength="40" value="<?= e(get($key)) ?>" placeholder="<?= e($placeholders[$key]) ?>">
                </label>
            <?php endforeach; ?>
            <label class="check"><input type="checkbox" name="save" value="1"<?= $save ? ' checked' : '' ?>> Сохранить в card.png</label>
            <button type="submit">Создать</button>
        </form>

        <?php if (!$gd): ?>
            <p class="message">Расширение GD с FreeType не включено. Проверьте: php -m | grep gd</p>
        <?php else: ?>
            <p><img src="<?= e($src) ?>" width="600" height="300" alt="Визитная карточка"></p>
            <?php if ($save): ?>
                <p class="note">Файл card.png сохранён в папке проекта.</p>
            <?php endif; ?>
        <?php endif; ?>
    </main>
</body>
</html>
