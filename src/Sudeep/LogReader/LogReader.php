<?php namespace Sudeep\LogReader;

use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Sudeep\LogReader\Contracts\LogParser as LogParserInterface;
use Sudeep\LogReader\Entities\LogEntry;
use Sudeep\LogReader\Exceptions\UnableToRetrieveLogFilesException;
use Sudeep\LogReader\Levelable;

/**
 * The LogReader class.
 *
 * @package Sudeep\LogReader
 * @author Jackie Do <anhvudo@gmail.com>
 * @copyright 2017
 * @access public
 */
class LogReader
{
    /**
     * Stores the current environment to sort the log entries.
     *
     * @var string
     */
    public $AllClasses;
    /**
     * Store instance of Cache Repository for caching
     *
     * @var \Illuminate\Cache\Repository
     */
    protected $cache;
    /**
     * Store instance of Config Repository for working with config
     *
     * @var \Illuminate\Config\Repository
     */
    protected $config;
    /**
     * Store instance of Request for getting request input
     *
     * @var \Illuminate\Http\Request
     */
    protected $request;
    /**
     * Store instance of LogParser for parsing content of the log file
     *
     * @var \Sudeep\LogReader\LogParser
     */
    protected $parser;
    /**
     * Store instance of Levelable to filter logs entry by level
     *
     * @var \Sudeep\LogReader\Levelable
     */
    protected $levelable;
    protected $classable;

    protected $environment = null;

    /**
     * Stores the current level to sort the log entries.
     *
     * @var null|array
     */
    protected $level = null;

    protected $class = null;

    /**
     * The path to directory storing the log files.
     *
     * @var string
     */
    protected $path = '';

    /**
     * Stores the filename to search log files for.
     *
     * @var string
     */
    protected $filename = '';

    /**
     * The current log file path.
     *
     * @var string
     */
    protected $currentLogPath = '';

    /**
     * Stores the field to order the log entries in.
     *
     * @var string
     */
    protected $orderByField = '';

    /**
     * Stores the direction to order the log entries in.
     *
     * @var string
     */
    protected $orderByDirection = '';

    /**
     * Stores the bool whether or not to return read entries.
     *
     * @var bool
     */
    protected $includeRead = false;

    /**
     * Construct a new instance and set attributes.
     *
     * @param object $cache
     * @param object $config
     * @param object $request
     *
     * @return void
     */
    public function __construct(Cache $cache, Config $config, Request $request)
    {
        $this->cache = $cache;
        $this->config = $config;
        $this->request = $request;
        $this->levelable = new Levelable;
//        $this->classable = new Classable;
        $this->parser = new LogParser;

        $this->setLogPath ( $this->config->get ( 'log-reader.path', storage_path ( 'logs' ) ) );
        $this->setLogFilename ( $this->config->get ( 'log-reader.filename', 'laravel.log' ) );
        $this->setEnvironment ( $this->config->get ( 'log-reader.environment' ) );
        $this->setLevel ( $this->config->get ( 'log-reader.level' ) );
        $this->setClass ( $this->config->get ( 'log-reader.class' ) );
        $this->setOrderByField ( $this->config->get ( 'log-reader.order_by_field', '' ) );
        $this->setOrderByDirection ( $this->config->get ( 'log-reader.order_by_direction', '' ) );
    }

    /**
     * Sets the path to directory storing the log files.
     *
     * @param string $path
     *
     * @return void
     */
    public function setLogPath($path)
    {
        $this->path = $path;
    }

    /**
     * Sets the log filename to retrieve the logs data from.
     *
     * @param string $filename
     *
     * @return void
     */
    protected function setLogFilename($filename)
    {
        if (empty( $filename )) {
            $this->filename = '*.*';
        } else {
            $this->filename = $filename;
        }
    }

    /**
     * Setting the parser for structural analysis
     *
     * @param object $parser
     *
     * @return void
     */
    public function setLogParser(LogParserInterface $parser)
    {
        $this->parser = $parser;
    }

    /**
     * Get instance of Levelable
     *
     * @return \Sudeep\LogReader\Levelable
     */
    public function getLevelable()
    {
        return $this->levelable;
    }

    public function getClassable()
    {
        return $this->classable;
    }

    /**
     * Sets the environment to sort the log entries by.
     *
     * @param string $environment
     *
     * @return \Sudeep\LogReader\LogReader
     */
    public function environment($environment)
    {
        $this->setEnvironment ( $environment );

        return $this;
    }

    /**
     * Sets the level to sort the log entries by.
     *
     * @param mixed $level
     *
     * @return \Sudeep\LogReader\LogReader
     */
    public function level($level)
    {

        if (empty( $level )) {
            $level = [];
        } elseif (is_string ( $level )) {
            $level = explode ( ',', str_replace ( ' ', '', $level ) );
        } else {
            $level = is_array ( $level ) ? $level : func_get_args ();
        }

        $this->setLevel ( $level );

        return $this;
    }

    public function class($class)
    {

        if (is_null( $class )) {
            $class = [];
        }
        $this->setClass($class);

        return $this;
    }

    /**
     * Sets the filename to get log entries.
     *
     * @param string $filename
     *
     * @return \Sudeep\LogReader\LogReader
     */
    public function filename($filename)
    {

        $this->setLogFilename ( $filename );

        return $this;
    }

    /**
     * Alias of the withRead() method.
     *
     * @return \Sudeep\LogReader\LogReader
     */
    public function includeRead()
    {
        return $this->withRead ();
    }

    /**
     * Includes read entries in the log results.
     *
     * @return \Sudeep\LogReader\LogReader
     */
    public function withRead()
    {
        $this->setIncludeRead ( true );

        return $this;
    }

    /**
     * Sets the includeRead property.
     *
     * @param bool $bool
     *
     * @return void
     */
    protected function setIncludeRead($bool = false)
    {
        $this->includeRead = $bool;
    }

    /**
     * Sets the direction to return the log entries in.
     *
     * @param string $field
     * @param string $direction
     *
     * @return \Sudeep\LogReader\LogReader
     */
    public function orderBy($field, $direction = 'asc')
    {
        $this->setOrderByField ( $field );
        $this->setOrderByDirection ( $direction );

        return $this;
    }

    /**
     * Finds a logged error by it's ID.
     *
     * @param string $id
     *
     * @return mixed|null
     */
    public function find($id = '')
    {
        return $this->get ()->get ( $id );
    }

    /**
     * Returns a Laravel collection of log entries.
     *
     * @return Collection
     * @throws \Sudeep\LogReader\Exceptions\UnableToRetrieveLogFilesException
     *
     */
    public function get()
    {
        $entries = [];

        $files = $this->getLogFiles ();

        if (!is_array ( $files )) {
            throw new UnableToRetrieveLogFilesException( 'Unable to retrieve files from path: ' . $this->getLogPath () );
        }

        foreach ($files as $log) {
            /*
             * Set the current log path for easy manipulation
             * of the file if needed
             */
            $this->setCurrentLogPath ( $log['path'] );

            /*
             * Parse the log into an array of entries, passing in the level
             * so it can be filtered
             */


            $parsedLog = $this->parseLog ( $log['contents'], $this->getEnvironment (), $this->getLevel (), $this->getClass () );

            /*
             * Create a new LogEntry object for each parsed log entry
             */
            foreach ($parsedLog as $entry) {
                $newEntry = new LogEntry( $this->parser, $this->cache, $entry );

                /*
                 * Check if the entry has already been read,
                 * and if read entries should be included.
                 *
                 * If includeRead is false, and the entry is read,
                 * then continue processing.
                 */
                if (!$this->includeRead && $newEntry->isRead ()) {
                    continue;
                }

                $entries[$newEntry->id] = $newEntry;
            }
        }

        return $this->postCollectionModifiers ( new Collection( $entries ) );
    }

    /**
     * Retrieves all the data inside each log file from the log file list.
     *
     * @return array|bool
     */
    protected function getLogFiles()
    {
        $data = [];

        $files = $this->getLogFileList ();

        if (is_array ( $files )) {
            $count = 0;

            foreach ($files as $file) {
                $data[$count]['contents'] = file_get_contents ( $file );
                $data[$count]['path'] = $file;
                $count++;
            }

            return $data;
        }

        return false;
    }

    /**
     * Returns an array of log file paths.
     *
     * @param null|string $forceName
     *
     * @return bool|array
     */
    protected function getLogFileList($forceName = null)
    {
        $path = $this->getLogPath ();

        if (is_dir ( $path )) {

            /*
             * Matches files in the log directory with the special name'
             */
            $logPath = sprintf ( '%s%s%s', $path, DIRECTORY_SEPARATOR, $this->getLogFilename () );

            /*
             * Force matches all files in the log directory'
             */
            if (!is_null ( $forceName )) {
                $logPath = sprintf ( '%s%s%s', $path, DIRECTORY_SEPARATOR, $forceName );
            }

            return glob ( $logPath, GLOB_BRACE );
        }

        return false;
    }

    /**
     * Retrieves the path to directory storing the log files.
     *
     * @return string
     */
    public function getLogPath()
    {
        return $this->path;
    }

    /**
     * Retrieves the log filename property.
     *
     * @return string
     */
    public function getLogFilename()
    {
        return $this->filename;
    }

    /**
     * Parses the content of the file separating the errors into a single array.
     *
     * @param string $content
     * @param string $allowedEnvironment
     * @param array $allowedLevel
     *
     * @return array
     */
    protected function parseLog($content, $allowedEnvironment = null, $allowedLevel = [], $allowedClass = [])
    {

        $log = [];
        $parsed = $this->parser->parseLogContent ( $content );
        extract ( $parsed, EXTR_PREFIX_ALL, 'parsed' );
        if (empty( $parsed_headerSet )) {
            return $log;
        }

        $needReFormat = in_array ( 'Next', $parsed_headerSet );

        $newContent = null;

        foreach ($parsed_headerSet as $key => $header) {
            if (empty( $parsed_dateSet[$key] )) {
                $parsed_dateSet[$key] = $parsed_dateSet[$key - 1];
                $parsed_envSet[$key] = $parsed_envSet[$key - 1];
                $parsed_levelSet[$key] = $parsed_levelSet[$key - 1];
                $parsed_classSet[$key] = $parsed_classSet[$key - 1];
                $header = str_replace ( "Next", $parsed_headerSet[$key - 1], $header );
            }

            $newContent .= $header . ' ' . $parsed_bodySet[$key];


            if (is_null($allowedClass) ||(!is_null($allowedClass) && $allowedClass == $parsed_classSet[$key])) {
                $log[] = [
                    'environment' => $parsed_envSet[$key],
                    'level' => $parsed_levelSet[$key],
                    'date' => $parsed_dateSet[$key],
                    'file_path' => $this->getCurrentLogPath (),
                    'header' => $header,
                    'body' => substr_replace ( $parsed_bodySet[$key], '', '|', '1'),
                    'class' => $parsed_classSet[$key],
                ];

            }
        }


        if ($needReFormat) {
            file_put_contents ( $this->getCurrentLogPath (), $newContent );
        }


        return $log;
    }

    /**
     * Retrieves the currentLogPath property.
     *
     * @return string
     */
    public function getCurrentLogPath()
    {
        return $this->currentLogPath;
    }

    /**
     * Sets the currentLogPath property to
     * the specified path.
     *
     * @param string $path
     *
     * @return void
     */
    protected function setCurrentLogPath($path)
    {
        $this->currentLogPath = $path;
    }

    /**
     * Retrieves the environment property.
     *
     * @return string
     */
    public function getEnvironment()
    {
        return $this->environment;
    }

    /**
     * Sets the environment property to the specified environment.
     *
     * @param string $environment
     *
     * @return void
     */
    protected function setEnvironment($environment)
    {
        $this->environment = $environment;
    }

    /**
     * Retrieves the level property.
     *
     * @return array
     */
    public function getLevel()
    {
        return $this->level;
    }

    /**
     * Sets the level property to the specified level.
     *
     * @param array $level
     *
     * @return void
     */
    protected function setLevel($level)
    {
        if (is_array ( $level )) {
            $this->level = $level;
        }
    }

    public function getClass()
    {
        return $this->class;
    }

    protected function setClass($class)
    {
        $this->class = $class;
    }

    /**
     * Modifies and returns the collection result if modifiers are set
     * such as an orderBy.
     *
     * @param Collection $collection
     *
     * @return Collection
     */
    protected function postCollectionModifiers(Collection $collection)
    {
        if ($this->getOrderByField () && $this->getOrderByDirection ()) {
            $field = $this->getOrderByField ();
            $desc = false;

            if ($this->getOrderByDirection () === 'desc') {
                $desc = true;
            }

            $sorted = $collection->sortBy ( function ($entry) use ($field) {
                if (property_exists ( $entry, $field )) {
                    return $entry->{$field};
                }
            }, SORT_NATURAL, $desc );

            return $sorted;
        }

        return $collection;
    }

    /**
     * Retrieves the orderByField property.
     *
     * @return string
     */
    public function getOrderByField()
    {
        return $this->orderByField;
    }

    /**
     * Sets the orderByField property to the specified field.
     *
     * @param string $field
     *
     * @return void
     */
    protected function setOrderByField($field)
    {
        $field = strtolower ( $field );

        $acceptedFields = [
            'id',
            'date',
            'level',
            'class',
            'environment',
            'file_path'
        ];

        if (in_array ( $field, $acceptedFields )) {
            $this->orderByField = $field;
        }
    }

    /**
     * Retrieves the orderByDirection property.
     *
     * @return string
     */
    public function getOrderByDirection()
    {
        return $this->orderByDirection;
    }

    /**
     * Sets the orderByDirection property to the specified direction.
     *
     * @param string $direction
     *
     * @return void
     */
    protected function setOrderByDirection($direction)
    {
        $direction = strtolower ( $direction );

        if ($direction == 'desc' || $direction == 'asc') {
            $this->orderByDirection = $direction;
        }
    }

    /**
     * Alias of the markAsRead() method.
     *
     * @return int
     */
    public function markRead()
    {
        return $this->markAsRead ();
    }

    /**
     * Marks all retrieved log entries as read and
     * returns the number of entries that have been marked.
     *
     * @return int
     */
    public function markAsRead()
    {
        $entries = $this->get ();

        $count = 0;

        foreach ($entries as $entry) {
            if ($entry->markAsRead ()) {
                ++$count;
            }
        }

        return $count;
    }

    /**
     * Deletes all retrieved log entries and returns
     * the number of entries that have been deleted.
     *
     * @return int
     */
    public function delete()
    {
        $entries = $this->get ();

        $count = 0;

        foreach ($entries as $entry) {
            if ($entry->delete ()) {
                ++$count;
            }
        }

        return $count;
    }

    /**
     * Deletes all retrieved log entries and returns
     * the number of entries that have been deleted.
     *
     * @return int
     */
    public function removeLogFile()
    {
        $files = $this->getLogFileList ();

        $count = 0;

        foreach ($files as $file) {
            if (@unlink ( $file )) {
                ++$count;
            }
        }

        return $count;
    }

    /**
     * Paginates the returned log entries.
     *
     * @param int $perPage
     * @param int $currentPage
     * @param array $options [path => '',, query => [], fragment => '', pageName => '']
     *
     * @return mixed
     */
    public function paginate($perPage = 25, $currentPage = null, array $options = [])
    {
        $currentPage = $this->getPageFromInput ( $currentPage, $options );
        $offset = ($currentPage - 1) * $perPage;
        $total = $this->count ();
        $entries = $this->get ()->slice ( $offset, $perPage )->all ();

        return new LengthAwarePaginator( $entries, $total, $perPage, $currentPage, $options );
    }

    /**
     * Returns the current page from the current input. Used for pagination.
     *
     * @param int $currentPage
     * @param array $options [path => '', query => [], fragment => '', pageName => '']
     *
     * @return int
     */
    protected function getPageFromInput($currentPage = null, array $options = [])
    {
        if (is_numeric ( $currentPage )) {
            return intval ( $currentPage );
        }

        $pageName = (array_key_exists ( 'pageName', $options )) ? $options['pageName'] : 'page';

        $page = $this->request->input ( $pageName );

        if (is_numeric ( $page )) {
            return intval ( $page );
        }

        return 1;
    }

    /**
     * Returns total of log entries.
     *
     * @return int
     */
    public function count()
    {
        return $this->get ()->count ();
    }

    /**
     * Returns an array of log filenames.
     *
     * @param null|string $filename
     *
     * @return array
     */
    public function getLogFilenameList($filename = null)
    {
        $data = [];

        if (empty( $filename )) {
            $filename = '*.*';
        }

        $files = $this->getLogFileList ( $filename );

        if (is_array ( $files )) {
            foreach ($files as $file) {
                $basename = pathinfo ( $file, PATHINFO_BASENAME );
                $data[$basename] = $file;
            }
        }

        return $data;
    }

    public function getclasses()
    {
        $files = $this->getLogFiles ();

        if (!is_array ( $files )) {
            throw new UnableToRetrieveLogFilesException( 'Unable to retrieve files from path: ' . $this->getLogPath () );
        }

        foreach ($files as $log) {
            $this->setCurrentLogPath ( $log['path'] );
            $parsed = $this->parser->parseLogContent ( $log['contents'] );
            extract ( $parsed, EXTR_PREFIX_ALL, 'parsed' );
        }
        return $parsed_classSet;

    }

}
