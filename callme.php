<?php

// Проверка на запуск из браузера
if (PHP_SAPI !== 'cli' || isset($_SERVER['HTTP_USER_AGENT'])) {
    die('Этот скрипт может быть запущен только из командной строки');
}

require __DIR__ . '/vendor/autoload.php';

use PAMI\Message\Event\EventMessage;
use PAMI\Message\Event\DialBeginEvent;
use PAMI\Message\Event\DialEndEvent;
use PAMI\Message\Event\NewchannelEvent;
use PAMI\Message\Event\VarSetEvent;
use PAMI\Message\Event\HangupEvent;

$helper = new HelperFuncs();
$callami = new CallAMI();

// Объект с глобальными массивами
$globalsObj = Globals::getInstance();

// Создаем экземпляр класса PAMI
$pamiClient = $callami->NewPAMIClient();
$pamiClient->open();

// Обрабатываем NewchannelEvent события
$pamiClient->registerEventListener(
    function (EventMessage $event) use ($helper, $globalsObj) {
        if (!$event instanceof NewchannelEvent) {
            return;
        }

        // Выгребаем параметры звонка
        $callUniqueid = $event->getUniqueid();
        $extNum = $event->getCallerIDNum();
        $CallChannel = $event->getChannel();

        // Добавляем звонок в массив, для обработки в других событиях
        $globalsObj->uniqueids[] = $callUniqueid;

        // Берем Exten из ивента
        $extention = $event->getExtension();

        // Логируем параметры звонка
        $helper->writeToLog(
            [
                'callUniqueid' => $callUniqueid,
                'extNum' => $extNum,
                'extention' => $extention,
                'CallChannel' => $CallChannel,
            ],
            'New NewchannelEvent call'
        );
    }
);

// Обрабатываем VarSetEvent события, получаем URL записи звонка
$pamiClient->registerEventListener(
    function (EventMessage $event) use ($helper, $globalsObj) {
        if (!$event instanceof VarSetEvent || !in_array($event->getUniqueID(), $globalsObj->uniqueids)) {
            return;
        }

        $variableName = $event->getVariableName();
        $value = $event->getValue();

        if ($variableName === 'FullFname' && preg_match('/^http.+$/', $value)) {
            $globalsObj->FullFnameUrls[$event->getUniqueID()] = $value;

            // Логируем URL записи звонка
            $helper->writeToLog(
                [
                    'callUniqueid' => $event->getUniqueID(),
                    'FullFnameUrls' => $value,
                ],
                'New VarSetEvent - get FullFname'
            );
        }

        if ($variableName === 'CallMeDURATION' && preg_match('/^\d+$/', $value)) {
            $globalsObj->Durations[$event->getUniqueID()] = $value;

            // Логируем продолжительность звонка
            $helper->writeToLog(
                [
                    'callUniqueid' => $event->getUniqueID(),
                    'Durations' => $value,
                ],
                'New VarSetEvent - get CallMeDURATION'
            );
        }

        if ($variableName === 'CallMeDISPOSITION' && preg_match('/^[A-Z\ ]+$/', $value)) {
            $globalsObj->Dispositions[$event->getUniqueID()] = $value;

            // Логируем disposition звонка
            $helper->writeToLog(
                [
                    'callUniqueid' => $event->getUniqueID(),
                    'Dispositions' => $value,
                ],
                'New VarSetEvent - get CallMeDISPOSITION'
            );
        }
    }
);

// Обрабатываем DialBeginEvent события
$pamiClient->registerEventListener(
    function (EventMessage $event) use ($helper, $globalsObj) {
        if (!$event instanceof DialBeginEvent || !in_array($event->getUniqueid(), $globalsObj->uniqueids)) {
            return;
        }

        // Выгребаем параметры звонка
        $intNum = $event->getDestCallerIDNum();
        $extNum = $event->getCallerIDNum();
        $CallChannel = $event->getChannel();

        if (preg_match('/^\d{3}$/', $intNum) && preg_match('/^\d{4,}$/', $extNum)) {
            if ($helper->getUSER_IDByIntNum($intNum)) {
                $globalsObj->intNums[$event->getUniqueid()] = $intNum;
            } elseif ($phoneInner = $helper->userPhoneInnerByPhoneInners($intNum)) {
                $globalsObj->intNums[$event->getUniqueid()] = $phoneInner;
            }

            // Регистриуем звонок в битриксе
            $globalsObj->calls[$event->getUniqueid()] = $helper->runInputCall($globalsObj->intNums[$event->getUniqueid()], $extNum);

            // Показываем карточку пользователю
            $helper->showInputCall($globalsObj->intNums[$event->getUniqueid()], $globalsObj->calls[$event->getUniqueid()]);

            $helper->writeToLog(
                [
                    'callUniqueid' => $event->getUniqueid(),
                    'intNum' => $intNum,
                    'globalsObj->intNums' => $globalsObj->intNums[$event->getUniqueid()],
                    'extNum' => $extNum,
                    'CALL_ID' => $globalsObj->calls[$event->getUniqueid()],
                    'CallChannel' => $CallChannel
                ],
                'New incoming call'
            );
        // } elseif (preg_match('/^\d{3}$/', $extNum) && preg_match('/^\d{4,}$/', $intNum)) {
        } else {
            if ($helper->getUSER_IDByIntNum($extNum)) {
                $globalsObj->intNums[$event->getUniqueid()] = $extNum;
            } elseif ($phoneInner = $helper->userPhoneInnerByPhoneInners($extNum)) {
                $globalsObj->intNums[$event->getUniqueid()] = $phoneInner;
            }

            // Регистрируем звонок в битриксе
            $globalsObj->calls[$event->getUniqueid()] = $helper->runOutputCall($globalsObj->intNums[$event->getUniqueid()], $intNum);

            // Показываем карточку пользователю
            $helper->showInputCall($globalsObj->intNums[$event->getUniqueid()], $globalsObj->calls[$event->getUniqueid()]);

            $helper->writeToLog(
                [
                    'callUniqueid' => $event->getUniqueid(),
                    'intNum' => $intNum,
                    'globalsObj->intNums' => $globalsObj->intNums[$event->getUniqueid()],
                    'extNum' => $extNum,
                    'CALL_ID' => $globalsObj->calls[$event->getUniqueid()],
                    'CallChannel' => $CallChannel
                ],
                'New outcoming call'
            );
        }
    }
);

// Обрабатываем DialEndEvent события
$pamiClient->registerEventListener(
    function (EventMessage $event) use ($helper, $globalsObj) {
        if (!$event instanceof DialEndEvent || !in_array($event->getUniqueid(), $globalsObj->uniqueids)) {
            return;
        }

        // Выгребаем параметры звонка
        $globalsObj->intNums[$event->getUniqueid()] = $event->getDestCallerIDNum();
        $extNum = $event->getCallerIDNum();

        switch ($event->getDialStatus()) {
            case 'ANSWER': // Кто-то отвечает на звонок
            case 'ANSWERED': // Кто-то отвечает на звонок
                $helper->writeToLog(
                    [
                        'callUniqueid' => $event->getUniqueid(),
                        'intNum' => $globalsObj->intNums[$event->getUniqueid()],
                        'extNum' => $extNum,
                        'CALL_ID' => $globalsObj->calls[$event->getUniqueid()],
                    ],
                    'incoming call ANSWER|ANSWERED'
                );

                // Для всех, кроме отвечающего, скрываем карточку
                $helper->hideInputCallExcept($globalsObj->intNums[$event->getUniqueid()], $globalsObj->calls[$event->getUniqueid()]);
                break;
            case 'BUSY': // Занято
                $helper->writeToLog(
                    [
                        'callUniqueid' => $event->getUniqueid(),
                        'intNum' => $globalsObj->intNums[$event->getUniqueid()],
                        'extNum' => $extNum,
                        'CALL_ID' => $globalsObj->calls[$event->getUniqueid()]
                    ],
                    'incoming call BUSY'
                );

                // Скрываем карточку для пользователя
                $helper->hideInputCall($globalsObj->intNums[$event->getUniqueid()], $globalsObj->calls[$event->getUniqueid()]);
                break;
            case 'CANCEL': // Звонивший бросил трубку
                $helper->writeToLog(
                    [
                        'callUniqueid' => $event->getUniqueid(),
                        'intNum' => $globalsObj->intNums[$event->getUniqueid()],
                        'extNum' => $extNum,
                        'CALL_ID' => $globalsObj->calls[$event->getUniqueid()],
                    ],
                    'incoming call CANCEL'
                );

                // Скрываем карточку для юзера
                $helper->hideInputCall($globalsObj->intNums[$event->getUniqueid()], $globalsObj->calls[$event->getUniqueid()]);
                break;
            default:
                break;
        }
    }
);

// Обрабатываем HangupEvent событие, отдаем информацию о звонке и URL его записи в Битрикс24
$pamiClient->registerEventListener(
    function (EventMessage $event) use ($helper, $globalsObj) {
        if (!$event instanceof HangupEvent || !in_array($event->getUniqueID(), $globalsObj->uniqueids)) {
            return;
        }

        $FullFname = $globalsObj->FullFnameUrls[$event->getUniqueID()];
        $CallDuration = $globalsObj->Durations[$event->getUniqueID()];
        $CallDisposition = $globalsObj->Dispositions[$event->getUniqueID()];
        $call_id = $globalsObj->calls[$event->getUniqueID()];
        $CallIntNum = $globalsObj->intNums[$event->getUniqueID()];

        // Логируем то, что мы собрались отдать Битрикс24
        $helper->writeToLog(
            [
                'callUniqueid' => $event->getUniqueID(),
                'FullFname' => $FullFname,
                'call_id' => $call_id,
                'intNum' => $CallIntNum,
                'Duration' => $CallDuration,
                'Disposition' => $CallDisposition,
            ],
            'New HangupEvent First step - recording filename URL, call_id, intNum, Duration, Disposition'
        );

        $resultFromB24 = $helper->uploadRecordedFile($call_id, $FullFname, $CallIntNum, $CallDuration, $CallDisposition);

        // Логируем, что нам рассказал Битрикс24 в ответ на наш запрос
        $helper->writeToLog($resultFromB24, 'New HangupEvent Second Step - upload filename');

        // Удаляем из массивов тот вызов, который завершился
        $helper->removeItemFromArray($globalsObj->uniqueids, $event->getUniqueID(), 'value');
        $helper->removeItemFromArray($globalsObj->intNums, $event->getUniqueID(), 'key');
        $helper->removeItemFromArray($globalsObj->FullFnameUrls, $event->getUniqueID(), 'key');
        $helper->removeItemFromArray($globalsObj->Durations, $event->getUniqueID(), 'key');
        $helper->removeItemFromArray($globalsObj->Dispositions, $event->getUniqueID(), 'key');
        $helper->removeItemFromArray($globalsObj->calls, $event->getUniqueID(), 'key');
    }
);

while (true) {
    $pamiClient->process();
    usleep($helper->getConfig('listener_timeout'));
}

$callami->ClosePAMIClient($pamiClient);
