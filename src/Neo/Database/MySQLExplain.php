<?php

namespace Neo\Database;

use Neo\NeoFrame;
use Neo\Str;
use NilPortugues\Sql\QueryFormatter\Formatter;

/**
 * Class MySQLExplain
 *
 * 复制了VBB的Explain类，并作改动
 */
class MySQLExplain extends MySQL
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
    public function execute(string $sql = null)
    {
        $sql = trim($sql);

        $this->timerStart('SQL Query');
        $stmt = parent::execute($sql);

        // 从PDO中解析出SQL，然后再explain
        if (method_exists($stmt, 'debugDumpParams')) {
            $sql = parent::getSQLFromDebugDumpParams($stmt);
        } else {
            $binds = [];
            foreach ($this->getBinds() as $bind) {
                if (! is_int($bind)) {
                    $binds[] = $this->quote($bind);
                } else {
                    $binds[] = $bind;
                }
            }
            $sql = vsprintf(str_ireplace('?', '%s', $sql), $binds);
        }

        $this->parseExplain($sql);

        $this->timerStop();

        return $stmt;
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
                    body { color: black; background-color: #FFF; }
                    body, p, td, th { font-family: verdana, sans-serif; font-size: 10pt; text-align: left; }
                    th { background: #F6F6F6; border-left: 1px solid #DDDDDD; }
                    td { border-left: 1px solid #DDDDDD; border-top: 1px solid #DDDDDD; }
                    div, pre, table { border: 1px solid #dddddd; border-collapse: separate; *border-collapse: collapse; border-left: 0;-webkit-border-radius: 4px; -moz-border-radius: 4px;border-radius: 4px; }
                    pre { padding: 8px; border-left: 1px solid #DDDDDD; }
                    div.explain { border: 1px solid #CCC; margin-bottom: 16px; }
                    div.explaintitle { color: black; background-color: white; padding: 4px; font-weight: bold; border-bottom: 1px solid #CCC; }
                    div.explainbody { padding: 8px; color: black; background-color: white; }
				</style>
			</head>
			<body>';
    }

    /**
     * @param string $str
     */
    private function output($str)
    {
        $this->message .= $str;
    }

    /**
     * explain
     *
     * @param string $sql
     */
    private function parseExplain($sql)
    {
        $results = stripos($sql, 'select') === 0 ? parent::explain($sql) : [];

        $sql = preg_replace('#\s+#', ' ', $sql);
        if (neo()->getExplainSQL() == 2) {
            $sql = (new Formatter())->format($sql);
        }

        $this->output("<pre>{$sql}</pre>");

        if (! $results) {
            return;
        }

        $this->output('<table style="width:100%"><tr>');
        foreach (array_keys($results[0]) as $field) {
            $this->output('<th>' . $field . '</th>');
        }
        $this->output('</tr>');
        foreach ($results as $result) {
            $this->output('<tr>');
            foreach ($result as $row) {
                $this->output('<td>' . ($row ?: '&nbsp;') . '</td>');
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
        $this->memory_before = memory_get_usage();
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

        $output = '<div class="explain"><div class="explaintitle">%s</div><div class="explainbody">%s</div></div>';
        echo sprintf($output, $this->message_title, $this->message);

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
            db(false)->getQueryCount(),
            Str::byteFormat(memory_get_usage(), 3),
            $includedFileCount,
            implode(
                '',
                preg_replace('#^(.*)$#si', '<li>\1</li>', array_map('removeSysPath', $get_included_files))
            )
        );

        echo $explain;
    }
}
