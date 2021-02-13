<?php

namespace letsjump\workdayHelper;

/**
 * Class WorkdayHelper
 *
 * Returns the number of the days worked and the holidays
 * plus an array with the custom closures
 * for a specific range of dates.
 *
 * Inspired by Massimo Simonini getWorkdays() Function
 *
 * @see     https://gist.github.com/massiws/9593008
 *
 * It also has:
 * - the possibility to specify any worked day in a week (see $shift)
 * - a method to add custom holidays or business closures, also as a result of a database query
 * - a way to return a calendar of holidays for that specific range of date
 * - the possibility to use your custom holiday calendar (see $publicHolidays)
 * - it take care to the timezone of your application
 * - it calculates the easter and the easter monday dates taking care of the timezone
 *
 * CALENDAR:
 * @note    if you want to retrive all the closing days you need to set all the days of the week
 * into the $shift Array E.G. $myWorkDay->shift[0,1,2,3,4,5,6].
 * Output format:
 *       [
 *          [1609455600] => [
 *              [unixTimestamp] => 1609455600,
 *              [date] => 2021-01-01, # control the format with $outputFormat property
 *              [event] => Capodanno, # description of the event
 *              [type] => public, #public / custom
 *              [options] => # custom option passed by the $customClosing Array
 *          ],
 *
 *          ...
 *       ]
 *
 * @warning The automatic easter calculator requires php compiled with --enable-calendar
 * @see     https://stackoverflow.com/questions/5297894/fatal-error-call-to-undefined-function-easter-date/51609625
 *
 *  =================
 *  USAGE:
 *  =================
 *
 * 1. count the day worked in january while working from monday to friday, taking care of public holidays:
 *
 * $closingDays                 = new WorkdayHelper('2021-01-01', '2021-01-31');
 * $closingDays->shift          = [1, 2, 3, 4, 5];
 * echo $closingDays->getWorkdays();
 *
 *  =================
 *
 * 2. count the day worked in april while working monday, wednesday and friday, taking care of public holidays:
 *
 * $closingDays                 = new WorkdayHelper('2021-04-01', '2021-04-30');
 * $closingDays->shift          = [1, 3, 5];
 * echo $closingDays->getWorkdays();
 *
 *  =================
 *
 * 3. Add a strike to the custom closing days
 *
 * $closingDays                 = new WorkdayHelper('2021-01-01', '2021-01-31');
 * $closingDays->shift          = [1, 2, 3, 4, 5];
 * $closingDays->customClosing = [
 *      [
 *          'date'    => '2021-01-18',
 *          'event'   => 'Strike!',
 *          'options' => [
 *              'id'        => 345,
 *              'htmlClass' => 'green'
 *          ]
 *      ],
 * ];
 * echo $closingDays->getWorkdays();
 *
 *  =================
 *  4. Get the calendar with all the closing days for a specific date interval
 *
 * $closingDays                 = new WorkdayHelper('2021-01-01', '2021-12-31');
 * $closingDays->shift          = [0, 1, 2, 3, 4, 5, 6]; // don't forget to set every day of the week!
 *
 * <table>
 * <?php foreach ($closingDays->getCalendar() as $holiday): ?>
 * <tr>
 *      <td><?= $holiday['date'] ?></td>
 *      <td><?= $holiday['event'] ?></td>
 * </tr>
 * <?php endforeach ?>
 * </table>
 *
 * @author  Gianpaolo Scrigna <letsjump@gmail.com>
 */
class WorkdayHelper
{
    public const TYPE_PUBLIC = 'public';
    public const TYPE_CUSTOM = 'custom';
    
    /**
     * @var int[] days to consider as worked
     * in a week, where monday = 0 and saturday = 6
     * @see
     */
    public $shift = [1, 2, 3, 4, 5];
    
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
     * @example
     * [
     *          [
     *              'date'    => '2021-01-05',
     *              'event'   => 'Sciopero generale',
     *              'options' => [
     *                   'id'        => 345,
     *                   'htmlClass' => 'green'
     *              ]
     *          ],
     *          [
     *              'date'  => '2021-01-10',
     *              'event' => 'Chiusura per ferie'
     *          ],
     *          ...
     * ]
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
            'm-d'   => '01-01',
            'event' => 'Capodanno',
            'options' => [
                'htmlClass' => 'blue'
            ]
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
            $this->getClosing();
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
            $this->getClosing();
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
                    $options = $holiday['options'] ?? null;
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
     * @throws \InvalidArgumentException
     *
     * Calculate the easter days for the year passed
     */
    private function addEasterDates($year)
    {
        try {
            $equinox = new \DateTime($year . "-03-21");
            $easterObject = $equinox->add(new \DateInterval('P' . easter_days($year) . 'D'));
            $this->addClosing($easterObject, 'Pasqua', self::TYPE_PUBLIC);
            $easterMondayObject = $easterObject->add(new \DateInterval('P1D'));
            $this->addClosing($easterMondayObject, 'Lunedì dell\'Angelo', self::TYPE_PUBLIC);
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
    private function getClosing()
    {
        $this->addPublicHolidays();
        $this->addCustomClosing();
        $this->workdays = 0;
        for (
            $unixDay = $this->startDateObject->format('U'); $unixDay <= $this->endDateObject->format('U'); $unixDay = strtotime("+1 day",
            $unixDay)
        ) {
            $dayOfWeek = date("w", $unixDay);
            if (in_array((int)$dayOfWeek, $this->shift, true)) {
                if ( ! array_key_exists($unixDay, $this->closing)) {
                    $this->workdays++;
                } else {
                    $this->holidays[$unixDay] = $this->closing[$unixDay];
                }
            }
        }
    }
}