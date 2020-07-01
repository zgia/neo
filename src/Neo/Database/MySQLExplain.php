<?php

namespace Neo\Database;

use Neo\NeoFrame;
use Neo\Str;

/**
 * Class MySQLExplain
 *
 * 复制了VBB的Explain类，并作改动
 */
class MySQLExplain extends MySQLi
{
    public $message = '';

    public $message_title = '';

    public $memory_before = 0;

    public $time_start = 0;

    public $time_before = 0;

    public $time_total = 0;

    /**
     * MySQLExplain constructor
     */
    public function __construct()
    {
        parent::__construct();

        $this->time_start = microtime(true);

        $this->header();

        echo sprintf(
            '<h3>System loaded to here: %s ms</h3>',
            self::formatTime($this->time_start - Neo()->getTimeStart())
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getConnection(array $config)
    {
        $this->timerStart("Connect to Database <em>{$config['database']}</em> on Server <em>{$config['host']}</em>");
        $return = parent::getConnection($config);
        $this->timerStop();

        return $return;
    }

    /**
     * {@inheritdoc}
     */
    public function execute(string $sql)
    {
        $this->sql = trim($sql);

        if (stripos($this->sql, 'select') === 0) {
            $this->explain($this->sql);
        } else {
            $this->output("<pre>{$this->sql}</pre>");
        }

        $this->timerStart('SQL Query');
        $return = parent::execute($sql);
        $this->timerStop();

        return $return;
    }

    /**
     * Closes the connection to the database server
     *
     * @return int
     */
    public function close()
    {
        parent::close();

        return 1;
    }

    /**
     * 输出页面的Header
     */
    private function header()
    {
        echo '<!DOCTYPE html>
			<html lang="' . NeoFrame::language() . '">
			<head>
				<meta charset="' . NeoFrame::charset() . '">
				<title>NeoFrame</title>
				<style type="text/css">
				<!--
				body { color: black; background-color: #FFF; }
				body, p, td, th { font-family: verdana, sans-serif; font-size: 10pt; text-align: left; }
				th { background: #F6F6F6; border-left: 1px solid #DDDDDD; }
				td { border-left: 1px solid #DDDDDD; border-top: 1px solid #DDDDDD; }
				div, pre, table { border: 1px solid #dddddd; border-collapse: separate; *border-collapse: collapse; border-left: 0;-webkit-border-radius: 4px; -moz-border-radius: 4px;border-radius: 4px; }
				pre { padding: 8px; border-left: 1px solid #DDDDDD; }
				div.explain { border: 1px solid #CCC; margin-bottom: 16px; }
				div.explaintitle { color: black; background-color: white; padding: 4px; font-weight: bold; border-bottom: 1px solid #CCC; }
				div.explainbody { padding: 8px; color: black; background-color: white; }
				-->
				</style>
			</head>
			<body>';
    }

    /**
     * @param $str
     */
    private function output($str)
    {
        $this->message .= $str;
    }

    /**
     * explain
     *
     * @param $sql
     */
    private function explain($sql)
    {
        $results = $this->simpleQuery('EXPLAIN ' . $sql);

        $this->output('<pre>' . preg_replace('#\s+#', ' ', $sql) . '</pre>');
        $this->output('<table style="width:100%"><tr>');
        while ($field = mysqli_fetch_field($results)) {
            $this->output('<th>' . $field->name . '</th>');
        }
        $this->output('</tr>');
        $numfields = mysqli_num_fields($results);
        while ($result = $this->fetchRow($results)) {
            $this->output('<tr>');
            for ($i = 0; $i < $numfields; ++$i) {
                $this->output('<td>' . ($result["{$i}"] == '' ? '&nbsp;' : $result["{$i}"]) . '</td>');
            }
            $this->output('</tr>');
        }
        $this->output('</table>');
    }

    /**
     * @param string $str
     */
    private function timerStart($str = '')
    {
        $this->message_title = $str;

        if (function_exists('memory_get_usage')) {
            $this->memory_before = memory_get_usage();
        }
        $this->time_before = microtime(true);
    }

    /**
     * 格式化时间，返回毫秒
     *
     * @param float $time 秒
     *
     * @return string
     */
    private static function formatTime($time)
    {
        return number_format($time * 1000, 3);
    }

    /**
     * 耗时、内存统计
     */
    private function timerStop()
    {
        $pagestart = $this->time_start;
        $time_before = $this->time_before - $pagestart;
        $time_after = microtime(true) - $pagestart;
        $time_taken = $time_after - $time_before;
        $this->time_total += $time_taken;

        $this->output('<p>Time Before: ' . self::formatTime($time_before) . ' ms<br />');
        $this->output('Time After: ' . self::formatTime($time_after) . ' ms<br />');
        $this->output('Time Taken: ' . self::formatTime($time_taken) . ' ms<br />');
        $this->output('Time Total: ' . self::formatTime($this->time_total) . ' ms</p>');

        if (function_exists('memory_get_usage')) {
            $memory_after = memory_get_usage();

            $this->output('<p>Memory Before: ' . Str::byteFormat($this->memory_before) . '<br />');
            $this->output('Memory After: ' . Str::byteFormat($memory_after) . '<br />');
            $this->output('Memory Used: ' . Str::byteFormat($memory_after - $this->memory_before) . '</p>');
        }

        $output = '<div class="explain">
			<div class="explaintitle">' . $this->message_title . "</div>
			<div class=\"explainbody\">{$this->message}</div>
		</div>";

        echo $output;
        $this->message = '';

        flush();
        ob_flush();
    }

    /**
     * 显示所有内存使用和 Included Files
     */
    public static function display()
    {
        $totaltime = self::formatTime(getExecutionTime());
        $get_included_files = get_included_files();
        $includedFileCount = count($get_included_files);

        $explain = '<div class="explainbody">
					Page generated in %s seconds with %d queries <br />
					Memory use %s<br /><br /><strong>Included %d Files:</strong>
					<ul>%s</ul>
					</div>
					</body></html>';

        $explain = sprintf(
            $explain,
            $totaltime,
            neo()->db->getQueryCount(),
            Str::byteFormat(memory_get_usage(), 3),
            $includedFileCount,
            implode(
                '',
                preg_replace(
                    ['#' . NeoFrame::getAbsPath() . '#si', '#^(.*)$#si'],
                    ['', '<li>\1</li>'],
                    $get_included_files
                )
            )
        );

        echo $explain;
    }
}
