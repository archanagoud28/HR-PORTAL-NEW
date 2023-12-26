<?php

namespace App\Livewire;

use Livewire\Component;
use Carbon\Carbon;
use App\Models\LeaveRequest;
use App\Models\EmployeeDetails;
use App\Models\HolidayCalendar;
class LeaveCalender extends Component
{
    public $year;
    public $month;
    public $calendar;
    public $leaveData;
    public $restrictedHolidayData;
    public $generalHolidayData;
    public $leaveRequests;
    public $selectedDate;
    public $eventDetails;
    public $companyId;
    public $filterCriteria = null;
    public $leaveTransactions = [];

    // Other properties and methods...
    public function filterBy($criteria)
    {
        $this->filterCriteria = $criteria;
        // Reload leave transactions for the selected date
        $this->loadLeaveTransactions($this->selectedDate);

    }

    public function mount()
    {
        $this->year = now()->year;
        $this->month = now()->month;
        $this->leaveRequests = LeaveRequest::all();
        $this->filterCriteria = 'Me';
        $this->loadLeaveTransactions(now()->toDateString());
        $this->generateCalendar();
    }
    public function generateCalendar()
{
    $firstDay = Carbon::create($this->year, $this->month, 1);
    $daysInMonth = $firstDay->daysInMonth;
    $today = now();

    $calendar = [];
    $dayCount = 1;
    $publicHolidays = $this->getPublicHolidaysForMonth($this->year, $this->month);

    // Calculate the first day of the week for the current month
    $firstDayOfWeek = $firstDay->dayOfWeek;

    // Calculate the starting date of the previous month
    $startOfPreviousMonth = $firstDay->copy()->subMonth();

    // Fetch holidays for the previous month
    $publicHolidaysPreviousMonth = $this->getPublicHolidaysForMonth(
        $startOfPreviousMonth->year,
        $startOfPreviousMonth->month
    );

    // Calculate the last day of the previous month
    $lastDayOfPreviousMonth = $firstDay->copy()->subDay();

    for ($i = 0; $i < ceil(($firstDayOfWeek + $daysInMonth) / 7); $i++) {
        $week = [];
        for ($j = 0; $j < 7; $j++) {
            if ($i === 0 && $j < $firstDay->dayOfWeek) {
                // Add the days of the previous month
                $previousMonthDays = $lastDayOfPreviousMonth->copy()->subDays($firstDay->dayOfWeek - $j - 1);
                $week[] = [
                    'day' => $previousMonthDays->day,
                    'isToday' => false,
                    'isPublicHoliday' => in_array($previousMonthDays->toDateString(), $publicHolidaysPreviousMonth->pluck('date')->toArray()),
                    'isCurrentMonth' => false,
                    'isPreviousMonth' => true,
                    'backgroundColor' => '', // Initialize with an empty background color
                    'leaveCountMe' => 0,
                    'leaveCountMyTeam' => 0,
                    ];
            } elseif ($dayCount <= $daysInMonth) {
                // Add the days of the current month
                $isToday = $dayCount === $today->day && $this->month === $today->month && $this->year === $today->year;
                $isPublicHoliday = in_array(
                    Carbon::create($this->year, $this->month, $dayCount)->toDateString(),
                    $publicHolidays->pluck('date')->toArray()
                );
                
                $backgroundColor = $isPublicHoliday ? 'background-color: IRIS;' : '';
                
                $date = Carbon::create($this->year, $this->month, $dayCount)->toDateString();
                $leaveCountMe = 0;
                $leaveCountMyTeam = 0;

                if ($this->filterCriteria === 'Me') {
                    $leaveCountMe = $this->loadLeaveTransactions($date, 'Me');
                } elseif ($this->filterCriteria === 'MyTeam') {
                    $leaveCountMyTeam = $this->loadLeaveTransactions($date, 'MyTeam');
                }

                $week[] = [
                    'day' => $dayCount,
                    'isToday' => $isToday,
                    'isPublicHoliday' => $isPublicHoliday,
                    'isCurrentMonth' => true,
                    'isPreviousMonth' => false,
                    'backgroundColor' => $backgroundColor,
                    'leaveCountMe' => $leaveCountMe,
                    'leaveCountMyTeam' => $leaveCountMyTeam,
                ];
              
                $dayCount++;
            } else {
                // Add the days of the next month
                $week[] = [
                    'day' => $dayCount - $daysInMonth,
                    'isToday' => false,
                    'isPublicHoliday' => in_array($lastDayOfPreviousMonth->copy()->addDays($dayCount - $daysInMonth)->toDateString(), $this->getPublicHolidaysForMonth($startOfPreviousMonth->year, $startOfPreviousMonth->month)->pluck('date')->toArray()),
                    'isCurrentMonth' => false,
                    'isNextMonth' => true,
                    'backgroundColor' => '', // Initialize with an empty background color
                    'leaveCountMe' => 0,
                    'leaveCountMyTeam' => 0,
                ];
                $dayCount++;
            }
        }
        $calendar[] = $week;
    }

    $this->calendar = $calendar;
   
}
 
    protected function getPublicHolidaysForMonth($year, $month)
    {
        return HolidayCalendar::whereYear('date', $year)
            ->whereMonth('date', $month)
            ->get();
    }


    public function previousMonth()
    {
        $date = Carbon::create($this->year, $this->month, 1)->subMonth();
        $this->year = $date->year;
        $this->month = $date->month;
        $this->generateCalendar();
    }

    public function nextMonth()
    {
        $date = Carbon::create($this->year, $this->month, 1)->addMonth();
        $this->year = $date->year;
        $this->month = $date->month;
        $this->generateCalendar();
    }


    public function loadLeaveTransactions($date)
    {
        // Retrieve leave transactions for the selected date from the database
        $employeeId = auth()->guard('emp')->user()->emp_id;
        $companyId = auth()->guard('emp')->user()->company_id;
        $dateFormatted = preg_replace('/[^\d-]/', '', $date); // Remove non-digit characters except hyphen
        $dateFormatted = trim($dateFormatted); // Trim any leading or trailing spaces
        
        // Extract only the date part before the space
        $dateParts = explode(' ', $dateFormatted);
        $dateOnly = $dateParts[0]; // Take only the date part
        
        // Get only the first two characters for the date part
        $dateFormatted = substr($dateOnly, 0, 10);
    
        // Parse the cleaned date
        $dateFormatted = Carbon::parse($dateFormatted)->format('Y-m-d');
        
        $leaveCount = 0; // Initialize leave count variable
    
        // Filter data based on the selected filter type
        if ($this->filterCriteria === 'Me') { // Replace $value with your actual condition for 'Me'
            $leaveTransactions = LeaveRequest::with('employee')
                ->whereDate('from_date', '<=', $dateFormatted)
                ->whereDate('to_date', '>=', $dateFormatted) 
                ->where('emp_id', $employeeId)
                ->where('status', 'approved')
                ->get();
    
            $leaveCount = $leaveTransactions->count(); // Get the count of leave transactions
            // Pass the leave transactions and count to the view or return as needed
            $this->leaveTransactions = $leaveTransactions;
      
        } elseif($this->filterCriteria === 'MyTeam') { // Replace $value with your actual condition for 'MyTeam'
            // Fetch team members' emp_ids based on the manager_id
            $teamMembersIds = EmployeeDetails::where('manager_id', $employeeId)->pluck('emp_id');
            $leaveTransactions = LeaveRequest::with('employee')
                ->whereIn('emp_id', $teamMembersIds)
                ->where('from_date', '<=', $dateFormatted)
                ->where('to_date', '>=', $dateFormatted)
                ->where('status', 'approved')
                ->get();
    
            $leaveCount = $leaveTransactions->count(); // Get the count of leave transactions
            // Pass the leave transactions and count to the view or return as needed
            $this->leaveTransactions = $leaveTransactions;
         
        } else {
            $this->leaveTransactions = null; // Setting leave transactions as null for other conditions
        }
    
        return $leaveCount;
    }
    


        protected function getTeamOnLeaveDataForDay($day)
    {
        // Fetch team leave data from your database

        return LeaveRequest::where('from_date', $day)->get();
    }

    protected function isRestrictedHolidayForDay($day)
    {
        // Check if $day is a restricted holiday
    // return LeaveRequest::where('date', $day)->where('type', 'restricted')->exists();
    return LeaveRequest::where('from_date', $day)->get();
    }


    public function dateClicked($date)
    {
        $date = trim($date);
        $this->selectedDate = $this->year . '-' . $this->month . '-' . str_pad($date, 2, '0', STR_PAD_LEFT);  
        $this->loadLeaveTransactions($this->selectedDate);  
    }


    public function render()
    {   
       
        $this->leaveData = LeaveRequest::where('emp_id', auth()->guard('emp')->user()->emp_id)
        ->where('status', 'approved')
        ->get();    
        $holidays = $this->getHolidays();
        
       
        return view('livewire.leave-calender', [
            'holidays' => $holidays,
            'leaveTransactions'=>$this->leaveTransactions,
        ]);
       
    }

    public function getHolidays()
    {
       
        // Extract only the date part before the space
        $dateParts = explode(' ', $this->selectedDate);
        $dateOnly = $dateParts[0]; // Take only the date part

        // Get only the first two characters for the date part
        $dateFormatted = substr($dateOnly, 0, 10);

        // Parse the cleaned date
        $clickedDate = Carbon::parse($dateFormatted);

      
        return HolidayCalendar::whereDate('date', $clickedDate->toDateString())->get();
    }
    public  function calculateNumberOfDays($fromDate, $fromSession, $toDate, $toSession)
    {
        try {
        
            $startDate = Carbon::parse($fromDate);
            $endDate = Carbon::parse($toDate);
            // Check if the start and end sessions are different on the same day
            if ($startDate->isSameDay($endDate) && $this->getSessionNumber($fromSession) === $this->getSessionNumber($toSession)) {
                // Inner condition to check if both start and end dates are weekdays
                if (!$startDate->isWeekend() && !$endDate->isWeekend()) {
                    return 0.5;
                } else {
                    // If either start or end date is a weekend, return 0
                    return 0;
                }
            }
            // Check if the start and end sessions are different on the same day
            if (
                
                $startDate->isSameDay($endDate) &&
                $this->getSessionNumber($fromSession) === $this->getSessionNumber($toSession)
            ) {
              
                // Inner condition to check if both start and end dates are weekdays
                if (!$startDate->isWeekend() && !$endDate->isWeekend()) {
                    return 0.5;
                } else {
                    // If either start or end date is a weekend, return 0
                    return 0;
                }
            }
            if (
                $startDate->isSameDay($endDate) &&
                $this->getSessionNumber($fromSession) !== $this->getSessionNumber($toSession)
            ) {
                
                // Inner condition to check if both start and end dates are weekdays
                if (!$startDate->isWeekend() && !$endDate->isWeekend()) {
                    return 1;
                } else {
                    // If either start or end date is a weekend, return 0
                    return 0;
                }
            }
            $totalDays = 0;

            while ($startDate->lte($endDate)) {
                // Check if it's a weekday (Monday to Friday)
                if ($startDate->isWeekday()) {
                    $totalDays += 1;
                }

                // Move to the next day
                $startDate->addDay();
            }

            // Deduct weekends based on the session numbers
            if ($this->getSessionNumber($fromSession) > 1) {
                $totalDays -= $this->getSessionNumber($fromSession) - 1; // Deduct days for the starting session
            }
            if ($this->getSessionNumber($toSession) < 2) {
                $totalDays -= 2 - $this->getSessionNumber($toSession); // Deduct days for the ending session
            }
            // Adjust for half days
            if ($this->getSessionNumber($fromSession) === $this->getSessionNumber($toSession)) {
                // If start and end sessions are the same, check if the session is not 1
                if ($this->getSessionNumber($fromSession) !== 1) {
                    $totalDays += 0.5; // Add half a day
                }
            }elseif($this->getSessionNumber($fromSession) !== $this->getSessionNumber($toSession)){
                if ($this->getSessionNumber($fromSession) !== 1) {
                    $totalDays += 1; // Add half a day
                }
            }
            else {
                $totalDays += ($this->getSessionNumber($toSession) - $this->getSessionNumber($fromSession) + 1) * 0.5;
            }

            return $totalDays;
            

        } catch (\Exception $e) {
            return 'Error: ' . $e->getMessage();
        }
    }

    private function getSessionNumber($session)
    {
        // You might need to customize this based on your actual session values
        return (int) str_replace('Session ', '', $session);
    }

    
}
