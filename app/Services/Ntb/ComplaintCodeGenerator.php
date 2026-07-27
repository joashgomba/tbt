<?php

namespace App\Services\Ntb;

use App\Models\Complaint;
use Illuminate\Support\Facades\DB;

class ComplaintCodeGenerator
{
    public function generate(): string
    {
        $year = now()->year;
        $prefix = "NTB-{$year}-";

        $last = Complaint::query()
            ->where('complaint_code', 'like', "{$prefix}%")
            ->orderByDesc('id')
            ->value('complaint_code');

        $sequence = 1;
        if ($last && preg_match('/(\d+)$/', $last, $matches)) {
            $sequence = ((int) $matches[1]) + 1;
        }

        return $prefix.str_pad((string) $sequence, 5, '0', STR_PAD_LEFT);
    }
}
