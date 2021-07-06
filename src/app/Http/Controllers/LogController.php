<?php

namespace Romansev\BackpackLogs\app\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Romansev\BackpackLogs\app\Classes\LogViewer;

class LogController extends Controller
{

    /**
     * Lists all log files.
     *
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function index()
    {
        $this->data['allFiles'] = $this->getFiles();
        $this->data['title'] = trans('romansev::logmanager.log_manager');

        return view('logmanager::logs', $this->data);
    }

    /**
     * Previews a log file.
     *
     * @throws \Exception
     */
    public function preview($file_name)
    {
        $logsPath = storage_path('logs');
        $file = $logsPath.'/'.base64_decode($file_name);

        $logs = $this->getLog($file);

        if (count($logs) <= 0) {
            abort(404, trans('romansev::logmanager.log_file_doesnt_exist'));
        }

        $this->data['allLogs'] = $logs;
        $this->data['title'] = trans('romansev::logmanager.preview').' '.trans('romansev::logmanager.logs');
        $this->data['file_name'] = base64_decode($file_name);

        return view('logmanager::log_item', $this->data);
    }

    /**
     * Downloads a log file.
     *
     * @param $file_name
     *
     * @throws \Exception
     *
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function download($file_name)
    {
        return response()->download(LogViewer::pathToLogFile(base64_decode($file_name)));
    }

    /**
     * Deletes a log file.
     *
     * @param $file_name
     *
     * @throws \Exception
     *
     * @return string
     */
    public function delete($file_name)
    {
        if (config('backpack.logmanager.allow_delete') == false) {
            abort(403);
        }

        if (app('files')->delete(LogViewer::pathToLogFile(base64_decode($file_name)))) {
            return 'success';
        }

        abort(404, trans('romansev::logmanager.log_file_doesnt_exist'));
    }

    private function getLog($file)
    {
        $log = [];


        $file = app('files')->get($file);

        $pattern = '/\[\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}.\d{6}\].*/';

        preg_match_all($pattern, $file, $headings);

        if (!is_array($headings)) {
            return $log;
        }

        $stack_trace = preg_split($pattern, $file);

        if ($stack_trace[0] < 1) {
            array_shift($stack_trace);
        }

        foreach ($headings as $h) {
            $h = array_slice($h, -50);
            for ($i = 0, $j = count($h); $i < $j; $i++) {
                foreach (LogViewer::$log_levels as $level) {
                    if (strpos(strtolower($h[$i]), '.'.$level) || strpos(strtolower($h[$i]), $level.':')) {
                        $pattern = '/^\[(?P<date>(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}.\d{6}))\](?:.*?(?P<context>(\w+))\.|.*?)'.$level.': (?P<text>.*?)(?P<in_file> in .*?:[0-9]+)?$/i';
                        preg_match($pattern, $h[$i], $current);
                        if (!isset($current['text'])) {
                            continue;
                        }

                        $jsonStart = strpos($current['text'], '{');
                        $text = substr($current['text'], $jsonStart);

                        $text = json_decode($text, true);
                        $response = $text['data']['answer'] ?? $text['data']['response'] ?? '';
                        if (is_array($response)) {
                            $text['data']['answer'] = $response;
                        } else {
                            $text['data']['answer'] = json_decode($response, true);
                        }

                        $method = $text['data']['method'];
                        $data = [
                            'method' => $method,
                            'request' => $text['data']['params'],
                            'response' => $text['data']['answer'],
                        ];

                        if (false !== strpos($method, 'railway')) {
                            $travel = 'railway';
                        } else {
                            $travel = 'avia';
                        }

                        $log[$travel][$method][] = [
                            'context'     => $current['context'],
                            'level'       => $level,
                            'level_class' => LogViewer::$levels_classes[$level],
                            'level_img'   => LogViewer::$levels_imgs[$level],
                            'date'        => $current['date'],
                            'text'        => json_encode($data),
                            'in_file'     => isset($current['in_file']) ? $current['in_file'] : null,
                            'stack'       => ''
                        ];
                    }
                }
            }
        }

        return array_reverse($log);
    }

    private function getFiles()
    {
        $filesAnswers = glob(storage_path().'/logs/*ANSWERS*.log');
        $filesAnswers = array_slice($filesAnswers, -3);
        $filesErrors = glob(storage_path().'/logs/*TRAVEL_API*.log');
        $filesErrors = array_slice($filesErrors, -3);
        $files = array_merge($filesAnswers, $filesErrors);

        $files = array_reverse($files);
        $files = array_filter($files, 'is_file');
        $allFiles = [];

        if (is_array($files)) {
            foreach ($files as $k => $file) {
                $disk = Storage::disk(config('backpack.base.root_disk_name'));
                $baseFileName = basename($file);

                $file_name = $baseFileName;
                $group = 'common';
                if (false !== strpos($baseFileName, 'TRAVEL_API')) {
                    $file_name = 'Ошибки поставщика' . substr($baseFileName, 18);
                    $group = 'errors';
                } elseif (false !== strpos($baseFileName, 'API_ANSWERS')) {
                    $file_name = 'Ответы поставщика' . substr($baseFileName, 19);
                    $group = 'answers';
                }

                if ($disk->exists('storage/logs/'.$baseFileName)) {
                    $allFiles[$group][$k] = [
                        'file_name'     => $file_name,
                        'orig_name'     => $baseFileName,
                        'file_size'     => $disk->size('storage/logs/'.$baseFileName),
                        'last_modified' => $disk->lastModified('storage/logs/'.$baseFileName),
                    ];
                }
            }
        }

        return array_values($allFiles);
    }
}
