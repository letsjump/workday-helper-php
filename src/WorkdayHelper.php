<?php

namespace letsjump\workdayHelper;

use yii\base\InvalidConfigException;

/**
 * Class WorkdayHelper
 *
 * Count work days and list holiday events in a range of dates with PHP taking care of public holidays and other custom
 * closing days.
 * Inspired by Massimo Simonini getWorkdays() Function. See https://gist.github.com/massiws/9593008
 *
 * @author  Gianpaolo Scrigna <letsjump@gmail.com>
 */
class WorkdayHelper
{
    public const TYPE_PUBLIC = 'public';
    public const TYPE_CUSTOM = 'custom';
    
    /**
     * @var int[] days to consider as worked
     * in a week, where sunday == 0 and saturday == 6
     * @see
     */
    public $workingDays = [1, 2, 3, 4, 5];
    
    /**
     * @var string date format for the closing days output list
     */
    public $outputFormat = 'Y-m-d';
    
    /**
     * @var bool calculate and add the easter dates
     *           to the closing days output list
     */
    public $calculateEaster = true;
    
    /**
     * @var array[] of custom closures.
     *            Any custom closure array need at least the keys:
     *            - date ([date] a date in the Y-m-d format)
     *            - event ([string] name of the event)
     * The optional array key `options` can contain any custom variable you need
     * and it will be passed as is to the closing days output list
     */
    public $customClosing = [];
    
    /**
     * @var array[] array of public holiday dates where:
     *               key: [date] date in m-d format
     *               value: [string] name of the event
     *
     */
    public $publicHolidays = [
        [
            'm-d'     => '01-01',
            'event'   => 'Capodanno',
        ],
        [
            'm-d'   => '01-06',
            'event' => 'Epifania'
        ],
        [
            'm-d'   => '04-25',
            'event' => 'Festa della Liberazione'
        ],
        [
            'm-d'   => '05-01',
            'event' => 'Festa del Lavoro'
        ],
        [
            'm-d'   => '06-02',
            'event' => 'Festa della Repubblica'
        ],
        [
            'm-d'   => '08-15',
            'event' => 'Ferragosto'
        ],
        [
            'm-d'   => '11-01',
            'event' => 'Ognissanti'
        ],
        [
            'm-d'   => '12-08',
            'event' => 'Immacolata'
        ],
        [
            'm-d'   => '12-25',
            'event' => 'Natale'
        ],
        [
            'm-d'   => '12-26',
            'event' => 'Santo Stefano'
        ],
    ];
    
    private $startDateObject;
    private $endDateObject;
    private $years = [];
    private $closing = [];
    private $workdays = null;
    private $holidays = [];
    
    public function __construct($startDate, $endDate)
    {
        try {
            $this->startDateObject = new \DateTime($startDate);
            $this->endDateObject   = new \DateTime($endDate);
        } catch (\Exception $e) {
            var_dump($e->getMessage());
            exit;
        }
        $this->getYearsInterval();
        
    }
    
    /**
     * @return integer the number of the worked days between the interval of dates.
     */
    public function getWorkdays()
    {
        if ($this->workdays === null) {
            $this->run();
        }
        
        return $this->workdays;
    }
    
    /**
     * @return array the array of all the closing days between the interval of dates.
     * @throws \InvalidArgumentException
     */
    public function getCalendar()
    {
        if ($this->workdays === null) {
            $this->run();
        }
        
        return $this->holidays;
    }
    
    /**
     * Fill the array $this->years with every year from the date interval passed
     */
    private function getYearsInterval()
    {
        for ($year = $this->startDateObject->format('Y'); $year <= $this->endDateObject->format('Y'); $year++) {
            $this->years[] = $year;
        }
    }
    
    /**
     * @param \DateTime $dateObject
     * @param string $description
     * @param string $type
     * @param null $options
     *
     * Add an item to $this->closing array
     */
    private function addClosing($dateObject, $description, $type, $options = null)
    {
        $unixTimestamp                 = $dateObject->format('U');
        $this->closing[$unixTimestamp] = [
            'unixTimestamp' => $unixTimestamp,
            'date'          => $dateObject->format($this->outputFormat),
            'event'         => $description,
            'type'          => $type,
            'options'       => $options
        ];
    }
    
    /**
     * Add the public holidays to the closing Array
     *
     * @throws \InvalidArgumentException
     */
    private function addPublicHolidays()
    {
        foreach ($this->years as $year) {
            foreach ($this->publicHolidays as $holiday) {
                try {
                    if ( ! array_key_exists('m-d', $holiday) || ! array_key_exists('event', $holiday)) {
                        throw new \InvalidArgumentException('Malformed PublicHoliday array. m-d or event key doesn\'t exists');
                    }
                    $dateObject = new \DateTime($year . '-' . $holiday['m-d']);
                    $options    = $holiday['options'] ?? null;
                    $this->addClosing($dateObject, $holiday['event'], self::TYPE_PUBLIC, $options);
                } catch (\Exception $e) {
                    var_dump($e->getMessage());
                }
            }
            if ($this->calculateEaster === true) {
                $this->addEasterDates($year);
            }
        }
    }
    
    /**
     * @param integer $year
     *
     * @throws \InvalidArgumentException
     *
     * Calculate the easter days for the year passed
     */
    private function addEasterDates($year)
    {
        try {
            if(function_exists('easter_days')) {
                $equinox      = new \DateTime($year . "-03-21");
                $easterObject = $equinox->add(new \DateInterval('P' . easter_days($year) . 'D'));
                $this->addClosing($easterObject, 'Pasqua', self::TYPE_PUBLIC);
                $easterMondayObject = $easterObject->add(new \DateInterval('P1D'));
                $this->addClosing($easterMondayObject, 'Lunedì dell\'Angelo', self::TYPE_PUBLIC);
            } else {
                throw new InvalidConfigException("ext-calendar not found in your PHP installation");
            }
        } catch (\Exception $e) {
            var_dump($e->getMessage());
        }
    }
    
    /**
     * Add the custom closing day to the closing Array
     */
    private function addCustomClosing()
    {
        if ( ! empty($this->customClosing)) {
            foreach ($this->customClosing as $closure) {
                try {
                    if ( ! array_key_exists('date', $closure) || ! array_key_exists('event', $closure)) {
                        throw new \InvalidArgumentException('Malformed CustomClosure array. Date or event key doesn\'t exists');
                    }
                    if (($dateObject = new \DateTime($closure['date'])) !== false) {
                        $options = $closure['options'] ?? null;
                        $this->addClosing($dateObject, $closure['event'], self::TYPE_CUSTOM, $options);
                    }
                } catch (\Exception $e) {
                    var_dump($e->getMessage());
                }
            }
        }
    }
    
    /**
     * Calculate the closing days, the number of days worked and the closing days calendar.
     *
     * @throws \InvalidArgumentException
     */
    private function run()
    {
        $this->addPublicHolidays();
        $this->addCustomClosing();
        $this->workdays = 0;
        for (
            $unixDay = $this->startDateObject->format('U'); $unixDay <= $this->endDateObject->format('U'); $unixDay = strtotime("+1 day",
            $unixDay)
        ) {
            $dayOfWeek = date("w", $unixDay);
            if (in_array((int)$dayOfWeek, $this->workingDays, true)) {
                if ( ! array_key_exists($unixDay, $this->closing)) {
                    $this->workdays++;
                } else {
                    $this->holidays[$unixDay] = $this->closing[$unixDay];
                }
            }
        }
    }
}