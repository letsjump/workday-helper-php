# WorkdayHelper
Count work days in a range of dates with PHP while taking care of public holidays and other custom closing days and returns an array of every holiday found in that range.

Inspired by Massimo Simonini getWorkdays() Gist.
@see     https://gist.github.com/massiws/9593008

It also has:
- the possibility to specify any worked day in a week (see $shift)
- a method to add custom holidays or business closures, also as a result of a database query
- a way to return a calendar of holidays for that specific range of date
- the possibility to use your custom holiday calendar (see $publicHolidays)
- it take care to the timezone of your application
- it calculates the easter and the easter monday dates taking care of the timezone

CALENDAR:
@note    if you want to retrive all the closing days you need to set all the days of the week
into the $shift Array E.G. $myWorkDay->shift[0,1,2,3,4,5,6].

Output format:
```php
      [
         [1609455600] => [
             [unixTimestamp] => 1609455600,
             [date] => 2021-01-01, # control the format with $outputFormat property
             [event] => Capodanno, # description of the event
             [type] => public, #public / custom
             [options] => # custom option passed by the $customClosing Array
         ],
         ...
      ]
```

@warning The automatic easter calculator requires php compiled with --enable-calendar
@see     https://stackoverflow.com/questions/5297894/fatal-error-call-to-undefined-function-easter-date/51609625

 ## Usage:

1. count the day worked in january while working from monday to friday, taking care of public holidays:
```php
$closingDays                 = new WorkdayHelper('2021-01-01', '2021-01-31');
$closingDays->shift          = [1, 2, 3, 4, 5];
echo $closingDays->getWorkdays();
```

2. count the day worked in april while working monday, wednesday and friday, taking care of public holidays:

```php
$closingDays                 = new WorkdayHelper('2021-04-01', '2021-04-30');
$closingDays->shift          = [1, 3, 5];
echo $closingDays->getWorkdays();
```

3. Add a strike to the custom closing days

```php
$closingDays                 = new WorkdayHelper('2021-01-01', '2021-01-31');
$closingDays->shift          = [1, 2, 3, 4, 5];
$closingDays->customClosing = [
     [
         'date'    => '2021-01-18',
         'event'   => 'Strike!',
         'options' => [
             'id'        => 345,
             'htmlClass' => 'green'
         ]
     ],
];
echo $closingDays->getWorkdays();
```

4. Get the calendar with all the closing days for a specific date interval

```php
$closingDays                 = new WorkdayHelper('2021-01-01', '2021-12-31');
$closingDays->shift          = [0, 1, 2, 3, 4, 5, 6]; // don't forget to set every day of the week!

<table>
<?php foreach ($closingDays->getCalendar() as $holiday): ?>
<tr>
     <td><?= $holiday['date'] ?></td>
     <td><?= $holiday['event'] ?></td>
</tr>
<?php endforeach ?>
</table>
```