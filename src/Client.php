<?php

declare(strict_types=1);

namespace Siteworx\FlightAware;

use League\CLImate\CLImate;
use League\CLImate\Exceptions\InvalidArgumentException;

class Client
{

    private CLImate $climate;

    private FlightAwareSoapClient $soapClient;

    private const WSDL = 'https://flightxml.flightaware.com/soap/FlightXML2/wsdl';

    /**
     * Client constructor.
     * @throws \SoapFault
     */
    public function __construct()
    {
        $this->climate = new CLImate();

        $this->climate->arguments->add([
            'login' => [
                'prefix' => 'l',
                'longPrefix' => 'login',
                'required' => true,
                'description' => 'Your FlightAware Login'
            ],
            'key' => [
                'prefix' => 'k',
                'longPrefix' => 'key',
                'required' => true,
                'description' => 'Your FlightAware API Key'
            ],
            'action' => [
                'prefix' => 'a',
                'longPrefix' => 'action',
                'required' => true,
                'description' => 'The endpoint you would like to query'
            ],
            'params' => [
                'prefix' => 'p',
                'longPrefix' => 'params',
                'description' => 'The JSON encoded params of your request.'
            ],
            'help' => [
                'longPrefix' => 'help',
                'description' => 'List the available actions you can call.',
                'noValue' => true
            ]
        ]);

        try {
            $this->climate->arguments->parse();

        } catch (InvalidArgumentException $exception) {
            $this->climate->error($exception->getMessage());
            $this->climate->arguments->usage($this->climate);

            if ($this->climate->arguments->get('help')) {
                $this->printAvailableActions();
            }

            throw $exception;
        }


        try {
            $this->soapClient = new FlightAwareSoapClient(self::WSDL, [
                'login' => $this->climate->arguments->get('login'),
                'password' => $this->climate->arguments->get('key')
            ]);
        } catch (\SoapFault $e) {
            $this->climate->error($e->getMessage());
            throw $e;
        }
    }

    /**
     * @throws \JsonException
     */
    public function run(): void
    {
        $action = $this->climate->arguments->get('action');

        $params = $this->climate->arguments->get('params');

        if ($params !== null && $params !== '') {
            try {
                $params = json_decode($params, true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException $e) {
                $this->climate->error('Unable to parse params: ' . $e->getMessage());

                throw $e;
            }
        }

        $result = $this->$action($params);

        $this->climate->json($result);
    }

    /**
     * @param array $params
     * @return array
     * @throws \SoapFault
     * @url https://flightxml.flightaware.com/soap/FlightXML2/doc#op_AircraftType
     */
    private function callAircraftType(array $params): array
    {
        $type = $params['type'] ?? '';

        if ($type === '') {
            $this->climate->error('Missing Params: ' . 'type');

            throw new InvalidArgumentException('Missing Params');
        }

        try {
            $result = (array)$this->soapClient->AircraftType(['type' => $type]);

            return (array)$result['AircraftTypeResult'];
        } catch (\SoapFault $exception) {
            $this->climate->error($exception->getMessage());

            throw $exception;
        }
    }

    /**
     * @param array $params
     * @return array
     * @throws \SoapFault
     * @url https://flightxml.flightaware.com/soap/FlightXML2/doc#op_TailOwner
     */
    private function callTailOwner(array $params): array
    {

        $tailNumber = $params['ident'] ?? '';

        if ($tailNumber === '') {
            $this->climate->error('Missing Params: ' . 'ident');

            throw new InvalidArgumentException('Missing Params');
        }

        try {
            $result = (array)$this->soapClient->TailOwner(['ident' => $tailNumber]);

            return (array)$result['TailOwnerResult'];
        } catch (\SoapFault $exception) {
            $this->climate->error($exception->getMessage());

            throw $exception;
        }
    }

    /**
     * @param array $params
     * @return array
     * @throws \SoapFault
     * @url https://flightxml.flightaware.com/soap/FlightXML2/doc#op_AirlineFlightInfo
     */
    private function callAirlineFlightInfo(array $params): array
    {

        $flightId = $params['faFlightID'] ?? '';

        if ($flightId === '') {
            $this->climate->error('Missing Params: ' . 'faFlightID');

            throw new InvalidArgumentException('Missing Params');
        }

        try {
            $result = (array)$this->soapClient->AirlineFlightInfo(['faFlightID' => $flightId]);

            return (array)$result['AirlineFlightInfoResult'];
        } catch (\SoapFault $exception) {
            $this->climate->error($exception->getMessage());

            throw $exception;
        }
    }

    private function printAvailableActions(): void
    {

        $this->climate->info('Available Actions: ');

        $reflection = new \ReflectionClass(self::class);

        foreach ($reflection->getMethods(\ReflectionMethod::IS_PRIVATE) as $method) {
            if (strpos($method->name, 'call') !== false) {
                $attributes = $method->getDocComment();
                preg_match_all('^(@[a-zA-Z]+\s*[a-zA-Z0-9, ()_].*)^', $attributes, $matches, PREG_PATTERN_ORDER);

                $url = '';
                foreach ($matches[0] as $match) {
                    if (strpos($match, '@url') !== false) {
                        $url = trim(str_replace('@url', '', $match));
                    }
                }

                $this->climate->tab()->info($this->methodToAction($method->name) . ' ' . $url);
            }
        }
    }

    private function actionToMethod(string $action): string
    {
        return 'call' . str_replace(' ', '', ucwords(str_replace('-', ' ', $action)));
    }

    private function methodToAction(string $method): string
    {
        return
            strtolower(
                substr(
                    ltrim(
                        preg_replace('/[A-Z]([A-Z](?![a-z]))*/', '-$0',
                            str_replace('call', '', $method)), '-'), 0));
    }

    public function __call($name, $arguments)
    {
        $method = $this->actionToMethod($name);

        if (!\method_exists(self::class, $method)) {
            $this->climate->error('Invalid Action: ' . $name);

            $this->printAvailableActions();

            throw new \RuntimeException('Not a valid action');
        }

        return $this->$method($arguments[0] ?? []);
    }

}