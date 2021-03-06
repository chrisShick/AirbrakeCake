<?php
/**
 * Created by PhpStorm.
 * User: Chris
 * Date: 4/23/2015
 * Time: 10:54 AM
 */
namespace chrisShick\AirbrakeCake\Error;

use Cake\Core\Configure;
use Cake\Error\ErrorHandler;
use Cake\Routing\Router;
use Cake\Utility\Hash;
use Airbrake\Configuration as AirbrakeConfiguration;
use Airbrake\Client as AirbrakeClient;
use Airbrake\Notice as AirbrakeNotice;


class AirbrakeHandler extends ErrorHandler
{
    protected $_airbrake;

    /**
     * Constructor
     *
     * @param array $options The options for error handling.
     */
    public function __construct($options = [])
    {
        $options['debug']  = Configure::read('debug');
        parent::__construct($options);
    }

    /**
     * Creates a new Airbrake instance, or returns an instance created earlier.
     * You can pass options to Airbrake\Configuration by setting the AirbrakeCake.options
     * configuration property.
     *
     * For example to set the environment name:
     *
     * ```
     * Configure::write('AirbrakeCake.options', array(
     * 	'environmentName' => 'staging'
     * ));
     * ```
     *
     * @return Airbrake\Client
     */
    public function getAirbrakeInstance()
    {
        if (empty($this->_airbrake)) {
            $airbrakeOptions = Configure::read('AirbrakeCake');
            $apiKey = $airbrakeOptions['apiKey'];
            if(isset($airbrakeOptions['options'])){
                $options = $airbrakeOptions['options'];
            } else {
                $options = array();
            }
            $request = Router::getRequest();
            if ($request) {
                $options['component'] = $request->params['controller'];
                $options['action'] = $request->params['action'];
                $session = $request->session();
                if (!empty($session)) {
                    $options['extraParameters'] = Hash::get($options, 'extraParameters', []);
                    $options['extraParameters']['User']['id'] = $session->read('Auth.User.id');
                }
            }
            $config = new AirbrakeConfiguration($apiKey, $options);
            $this->_airbrake = new AirbrakeClient($config);
        }
        return $this->_airbrake;
    }

    /**
     * {@inheritDoc}
     */
    public function handleError($code, $description, $file = null, $line = null, $context = null)
    {
        if($this->_options['debug'] === false || Configure::read('AirbrakeCake.debugOption') === true ) {
            list($error) = self::mapErrorCode($code);
            $backtrace = debug_backtrace();
            if (count($backtrace) > 1) {
                array_shift($backtrace);
            }
            $notice = new AirbrakeNotice();
            $notice->load(array(
                'errorClass' => $error,
                'backtrace' => $backtrace,
                'errorMessage' => $description,
                'extraParams' => null,

            ));

            $airbreak = $this->getAirbrakeInstance();
            $airbreak->notify($notice);
        }
        return parent::handleError($code, $description, $file, $line, $context);
    }

    /**
     * {@inheritDoc}
     */
    public function handleException(\Exception $exception)
    {
        if($this->_options['debug'] === false || Configure::read('AirbrakeCake.debugOption') === true ) {
            $airbreak = $this->getAirbrakeInstance();
            $airbreak->notifyOnException($exception);
        }
        parent::handleException($exception);
    }
}
