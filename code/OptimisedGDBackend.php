<?php

use Symfony\Component\Process\Process;

/**
 * Class OptimisedGDBackend
 */
class OptimisedGDBackend extends GDBackend implements ImageOptimiserInterface
{
    /**
     * @var Config_ForClass
     */
    protected $config;
    /**
     * @var Raven_Client
     */
    public $logger;
    /**
     * @var array
     */
    public static $dependencies = array(
        'logger' => '%$Raven'
    );
    /**
     * @param null $filename
     */
    public function __construct($filename = null)
    {
        $this->config = $this->config();
        parent::__construct($filename);
    }
    /**
     * @param string $filename
     */
    public function writeTo($filename)
    {
        parent::writeTo($filename);

        $this->optimiseImage($filename);
    }
    /**
     * @param $filename
     */
    public function optimiseImage($filename)
    {
        if (file_exists($filename)) {
            list($width, $height, $type, $attr) = getimagesize($filename);

            $command = $this->getCommand(
                $filename,
                $this->getImageType($type)
            );

            if ($command) {
                $process = $this->execCommand($command);

                $debug = $this->config->get('debug');

                if (null !== $this->logger && (!$process->isSuccessful() || $debug)) {
                    $this->logger->capture(
                        array(
                            'message' => 'SilverStripe Optimised Image',
                            'extra' => array(
                                'exitCode' => $process->getExitCode(),
                                'output' => $process->getOutput(),
                                'errorOutput' => $process->getErrorOutput()
                            ),
                            'level' => !$process->isSuccessful() ? Raven_Client::ERROR : Raven_Client::INFO
                        ),
                        false
                    );
                }
            }
        }
    }
    protected function getImageType($type)
    {
        switch ($type) {
            case IMAGETYPE_JPEG:
                return 'jpg';
            case IMAGETYPE_PNG;
                return 'png';
            default:
                return false;
        }
    }
    /**
     * @param $filename
     * @param $type
     * @return bool|string
     */
    protected function getCommand($filename, $type)
    {
        $commands = $this->config->get('availableCommands');

        if (!$type || !isset($commands[$type])) {
            return false;
        }

        $command = false;

        foreach ((array) $this->config->get('enabledCommands') as $commandType) {
            if (isset($commands[$type][$commandType])) {
                $command = $commands[$type][$commandType];
                break;
            }
        }

        if (!$command) {
            return false;
        }

        $viewer = new SSViewer_FromString($command);

        return sprintf(
            '%s/%s',
            rtrim($this->config->get('binDirectory'), '/'),
            $viewer->process(
                new ArrayData(
                    array(
                        'Quality'  => $this->quality,
                        'Filename' => $filename
                    )
                )
            )
        );
    }
    /**
     * @param $command
     * @return \Symfony\Component\Process\Process
     */
    private function execCommand($command)
    {
        $process = new Process($command);
        $process->run();
        return $process;
    }
}
