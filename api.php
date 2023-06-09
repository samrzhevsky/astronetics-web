<?php

error_reporting(E_ALL);
header('Content-type: application/json');

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/utils.class.php';

$config = require __DIR__ . '/config.php';
$db = new Medoo\Medoo($config['db']);

if (!isset($_GET['action'])) {
    exit(json_encode(['status' => 0, 'error' => 'Empty action']));
} elseif (!isset($_GET['user_id']) || !preg_match('/^[0-9a-fA-F]{16}$/', $_GET['user_id'])) {
    exit(json_encode(['status' => 0, 'error' => 'Empty or invalid user_id']));
}

// Получение списка всех тестов пользователя
elseif ($_GET['action'] === 'getTests') {
    if (!$user = $db->get('users', ['id[Int]'], ['uuid' => $_GET['user_id']])) {
        // ограничение количества запросов от новых пользователей
        $userIp = Utils::getIp();
        $reqLimit = $db->get('request_limit', ['id[Int]', 'last_request[Int]', 'request_count[Int]'], ['ip' => $userIp]);

        if (!$reqLimit) {
            // новый запрос
            $db->insert('request_limit', [
                'ip' => $userIp,
                'last_request' => time(),
                'request_count' => 1
            ]);
        } elseif (($reqLimit['last_request'] > (time() - $config['requestTimeInterval'])) && ($reqLimit['request_count'] < $config['requestMax'])) {
            // запрос проходит по количеству запросов/время
            $db->update('request_limit', ['request_count[+]' => 1], ['id' => $reqLimit['id']]);
        } elseif ($reqLimit['last_request'] > (time() - $config['requestTimeInterval'])) {
            // запрос не прошёл по количеству запросов/время. Отсекаем его
            exit(json_encode(['status' => 0, 'error' => 'Повторите попытку позже']));
        } else {
            $db->update('request_limit', [
                'last_request' => time(),
                'request_count' => 1
            ], ['id' => $reqLimit['id']]);
        }

        // очистка старых записей
        $db->delete('request_limit', ['last_request[<]' => time() - (3 * 3600)]);
        // ---

        $db->insert('users', ['uuid' => $_GET['user_id']]);
        if (!$user = $db->get('users', ['id[Int]'], ['id' => $db->id()])) {
            exit(json_encode(['status' => 0, 'error' => 'User not found']));
        }

        // генерация тестов к каждому разделу
        foreach ($config['categoriesId'] as $categoryId) {
            $questions = $db->select('questions', 'id[Int]', ['category' => $categoryId]);
            if (count($questions) < $config['questionsInTest']) {
                continue;
            }

            $questionIds = [];
            for ($i = 0; $i < $config['questionsInTest']; ++$i) {
                $questionId = -1;

                do {
                    $questionId = $questions[array_rand($questions)];
                } while (in_array($questionId, $questionIds));

                $questionIds[] = $questionId;
            }

            $db->insert('tests', [
                'user_id' => $user['id'],
                'category' => $categoryId,
                'questions' => json_encode($questionIds)
            ]);
        }
    }

    $tests = $db->select('tests', ['id[Int]', 'category[Int]', 'completed[Bool]', 'result[Int]', 'locked_until[Int]'], ['user_id' => $user['id']]);

    exit(json_encode(['status' => 1, 'tests' => $tests]));
}

// Получение данных сгенерированного теста
elseif ($_GET['action'] === 'getTestById') {
    if (!isset($_GET['test_id'])) {
        exit(json_encode(['status' => 0, 'error' => 'Empty test_id']));
    } elseif (!$user = $db->get('users', ['id[Int]'], ['uuid' => $_GET['user_id']])) {
        exit(json_encode(['status' => 0, 'error' => 'User not found']));
    } elseif (!$test = $db->get('tests', ['id[Int]', 'category[Int]', 'questions[JSON]', 'answers[JSON]', 'completed[Bool]', 'result[Int]', 'locked_until[Int]'], ['user_id' => $user['id'], 'id' => $_GET['test_id']])) {
        exit(json_encode(['status' => 0, 'error' => 'Test not found']));
    }

    $questions = $test['questions'];
    $answers = $test['answers'];

    $response = [
        'status' => 1,
        'test' => [
            'id' => $test['id'],
            'completed' => $test['completed'],
            'result' => $test['result'],
            'category' => $test['category'],
            'locked_until' => $test['locked_until']
        ],
        'questions' => []
    ];

    foreach ($questions as $key => $question) {
        if (!$questionData = $db->get('questions', ['question', 'answers[JSON]', 'correct_answer[Int]'], ['id' => $question])) {
            continue;
        }

        $answers = [];
        $selectedAnswer = -1;
        foreach ($questionData['answers'] as $answKey => $answer) {
            $isSelected = false;

            if (($test['answers'][$key] ?? -1) === $answKey) {
                $isSelected = true;
                $selectedAnswer = $answKey;
            }

            $answers[] = [
                'answer' => $answer,
                'selected' => $isSelected
            ];
        }

        $response['questions'][] = [
            'question' => $questionData['question'],
            'answers' => $answers,
            'correct' => $selectedAnswer === $questionData['correct_answer']
        ];
    }

    exit(json_encode($response));
}

// регенерация теста для перепрохождения
elseif ($_GET['action'] === 'regenerateTest') {
    if (!isset($_GET['test_id'])) {
        exit(json_encode(['status' => 0, 'error' => 'Empty test_id']));
    } elseif (!$user = $db->get('users', ['id[Int]'], ['uuid' => $_GET['user_id']])) {
        exit(json_encode(['status' => 0, 'error' => 'User not found']));
    } elseif (!$test = $db->get('tests', ['id[Int]', 'category[Int]', 'completed[Bool]', 'locked_until[Int]'], ['user_id' => $user['id'], 'id' => $_GET['test_id']])) {
        exit(json_encode(['status' => 0, 'error' => 'Test not found']));
    }

    if (!$test['completed']) {
        exit(json_encode(['status' => 0, 'error' => 'Тест ещё не пройден']));
    } elseif ($test['locked_until'] === -1 || $test['locked_until'] > time()) {
        exit(json_encode(['status' => 0, 'error' => 'Перепрохождение недоступно']));
    }

    $questions = $db->select('questions', 'id[Int]', ['category' => $test['category']]);
    $questionIds = [];
    for ($i = 0; $i < $config['questionsInTest']; ++$i) {
        $questionId = -1;

        do {
            $questionId = $questions[array_rand($questions)];
        } while (in_array($questionId, $questionIds));

        $questionIds[] = $questionId;
    }

    $db->update('tests', [
        'completed' => 0,
        'result' => 0,
        'locked_until' => 0,
        'questions' => json_encode($questionIds),
        'answers' => null
    ], [
        'user_id' => $user['id'],
        'id' => $_GET['test_id']
    ]);

    exit(json_encode(['status' => 1]));
}

// Отправка своих ответов на проверку
elseif ($_GET['action'] === 'checkAnswers') {
    $jsonParams = file_get_contents('php://input');
    if (strlen($jsonParams) === 0 || !Utils::isValidJSON($jsonParams)) {
        exit(json_encode(['status' => 0, 'error' => 'Invalid data']));
    }
    $decodedParams = json_decode($jsonParams, true);

    if (!isset($decodedParams['test_id'])) {
        exit(json_encode(['status' => 0, 'error' => 'Empty test_id']));
    } elseif (!isset($decodedParams['answers']) || count($decodedParams['answers']) === 0) {
        exit(json_encode(['status' => 0, 'error' => 'Empty answers']));
    } elseif (!$user = $db->get('users', ['id[Int]', 'firstname', 'lastname'], ['uuid' => $_GET['user_id']])) {
        exit(json_encode(['status' => 0, 'error' => 'User not found']));
    } elseif (!$test = $db->get('tests', ['questions[JSON]', 'completed[Bool]'], ['user_id' => $user['id'], 'id' => $decodedParams['test_id']])) {
        exit(json_encode(['status' => 0, 'error' => 'Тест не найден']));
    } elseif ($test['completed']) {
        exit(json_encode(['status' => 0, 'error' => 'Тест уже пройден']));
    }

    $answers = $decodedParams['answers'];
    if (count($answers) !== count($test['questions'])) {
        exit(json_encode(['status' => 0, 'error' => 'Для отправки ответов на проверку необходимо ответить на все вопросы']));
    }

    $response = ['status' => 1, 'cert' => false, 'need_fill_profile' => false, 'result' => []];
    $result = 0;

    foreach ($test['questions'] as $key => $questionId) {
        if (!$questionData = $db->get('questions', ['correct_answer[Int]'], ['id' => $questionId])) {
            continue;
        }

        $answerIsCorrect = $questionData['correct_answer'] === $answers[$key];

        $result += $answerIsCorrect;
        $response['result'][$key] = $answerIsCorrect;
    }

    // результат ниже проходного
    if ($result < $config['passingScore']) {
        $lockTime = time() + $config['testRePassDelay'];
    } else {
        $lockTime = -1;

        // если пользователь прошёл все тесты (отправляемый - последний), то рисуем ему сертификат
        if ($db->count('tests', ['user_id' => $user['id'], 'OR' => ['completed' => 0, 'result[<]' => $config['passingScore']]]) === 1) {
            $db->update('users', [
                'cert_id' => mb_strtoupper(bin2hex(random_bytes(10))),
                'cert_date' => $db->raw('CURRENT_TIMESTAMP()')
            ], ['id' => $user['id']]);

            $response['cert'] = true;
            $response['need_fill_profile'] = is_null($user['firstname']) || is_null($user['lastname']);
        }
    }

    $db->update('tests', [
        'completed' => 1,
        'result' => $result,
        'answers' => json_encode($answers),
        'locked_until' => $lockTime
    ], [
        'id' => $decodedParams['test_id'],
        'user_id' => $user['id']
    ]);

    exit(json_encode($response));
}

elseif ($_GET['action'] === 'getProfile') {
    if (!$user = $db->get('users', ['firstname', 'lastname', 'midname', 'cert_saved[Bool]'], ['uuid' => $_GET['user_id']])) {
        exit(json_encode(['status' => 0, 'error' => 'User not found']));
    }

    exit(json_encode([
        'status' => 1,
        'firstname' => $user['firstname'] ?? '',
        'lastname' => $user['lastname'] ?? '',
        'midname' => $user['midname'] ?? '',
        'cert_saved' => $user['cert_saved']
    ]));
}

// изменение фио
elseif ($_GET['action'] === 'editProfile') {
    $jsonParams = file_get_contents('php://input');
    if (strlen($jsonParams) === 0 || !Utils::isValidJSON($jsonParams)) {
        exit(json_encode(['status' => 0, 'error' => 'Invalid data']));
    }
    $decodedParams = json_decode($jsonParams, true);

    if (!isset($decodedParams['firstname'])) {
        exit(json_encode(['status' => 0, 'error' => 'Empty firstname']));
    } elseif (!isset($decodedParams['lastname'])) {
        exit(json_encode(['status' => 0, 'error' => 'Empty lastname']));
    } elseif (!isset($decodedParams['midname'])) {
        exit(json_encode(['status' => 0, 'error' => 'Empty midname']));
    } elseif (!$user = $db->get('users', ['id[Int]', 'cert_saved[Bool]'], ['uuid' => $_GET['user_id']])) {
        exit(json_encode(['status' => 0, 'error' => 'User not found']));
    } elseif ($user['cert_saved']) {
        exit(json_encode(['status' => 0, 'error' => 'Изменение ФИО недоступно']));
    }

    $firstname = trim($decodedParams['firstname']);
    $lastname = trim($decodedParams['lastname']);
    $midname = trim($decodedParams['midname']);

    if (mb_strlen($firstname) > 255) {
        exit(json_encode(['status' => 0, 'error' => 'Превышена максимальная длина имени']));
    } elseif (mb_strlen($lastname) > 255) {
        exit(json_encode(['status' => 0, 'error' => 'Превышена максимальная длина фамилии']));
    } elseif (mb_strlen($midname) > 255) {
        exit(json_encode(['status' => 0, 'error' => 'Превышена максимальная длина отчества']));
    }

    if ($firstname === '') {
        $firstname = null;
    }
    if ($lastname === '') {
        $lastname = null;
    }
    if ($midname === '') {
        $midname = null;
    }

    $db->update('users', [
        'firstname' => $firstname,
        'lastname' => $lastname,
        'midname' => $midname
    ], ['id' => $user['id']]);

    exit(json_encode(['status' => 1]));
}

// получение рейтинга пользователя
elseif ($_GET['action'] === 'getRating') {
    if (!$user = $db->get('users', ['id[Int]', 'firstname', 'lastname', 'midname', 'cert_id', 'cert_saved[Bool]'], ['uuid' => $_GET['user_id']])) {
        exit(json_encode(['status' => 0, 'error' => 'Для отображения рейтинга необходимо пройти хотя бы один тест']));
    }

    $usersRating = $db->query('SELECT `user_id`, SUM(`result`) AS `score` FROM `tests` WHERE `completed` = 1 GROUP BY `user_id` ORDER BY `score` DESC')->fetchAll();
    $myRatingPosition = null;
    $myRatingScore = null;

    foreach ($usersRating as $position => $userRating) {
        if ($userRating['user_id'] === $user['id']) {
            $myRatingScore = $userRating['score'];
            $myRatingPosition = $position + 1;
            break;
        }
    }

    if (is_null($myRatingPosition)) {
        exit(json_encode(['status' => 0, 'error' => 'Для отображения рейтинга необходимо пройти хотя бы один тест']));
    }

    $isProfileFilled = !is_null($user['firstname']) && !is_null($user['lastname']);
    $fullName = $isProfileFilled ? (ucfirst($user['lastname']) . ' ' . ucfirst($user['firstname'])) : '';
    if ($isProfileFilled && !is_null($user['midname'])) {
        $fullName .= ' ' . ucfirst($user['midname']);
    }

    exit(json_encode([
        'status' => 1,
        'score' => $myRatingScore,
        'better' => ((count($usersRating) - $myRatingPosition) / count($usersRating)) * 100,
        'cert_url' => isset($user['cert_id']) ? ($config['certDownloadUrl'] . $user['cert_id']) : '',
        'cert_saved' => $user['cert_saved'],
        'profile_filled' => $isProfileFilled,
        'full_name' => $fullName,
        'total_tests' => $db->count('tests', ['user_id' => $user['id']]),
        'passed_tests' => $db->count('tests', ['user_id' => $user['id'], 'completed' => 1, 'result[>=]' => $config['passingScore']])
    ]));
}

// Проверка наличия обновлений
elseif ($_GET['action'] === 'checkForUpdates') {
    if (!isset($_GET['current']) || $_GET['current'] < 1) {
        exit(json_encode(['status' => 0, 'Empty or invalid current']));
    }

    $hasUpdates = $db->has('version', ['version[>]' => $_GET['current']]);

    exit(json_encode([
        'status' => 1,
        'has_updates' => $hasUpdates,
        'download_url' => $hasUpdates ? $config['updateDownloadUrl'] . ((int) $_GET['current']) : ''
    ]));
}

exit(json_encode(['status' => 0, 'error' => 'Unknown action: ' . $_GET['action']]));
