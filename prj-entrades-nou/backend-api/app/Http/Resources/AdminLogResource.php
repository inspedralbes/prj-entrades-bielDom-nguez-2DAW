<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Config;

class AdminLogResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $tzName = (string) Config::get('admin.business_timezone', 'Europe/Madrid');
        $at = $this->created_at->timezone($tzName);
        $name = '—';
        if ($this->relationLoaded('adminUser') && $this->adminUser !== null) {
            $name = $this->adminUser->profileDisplayName();
        }

        return [
            'id' => $this->id,
            'admin_name' => $name,
            'date' => $at->format('Y-m-d'),
            'time' => $at->format('H:i:s'),
            'ip_address' => $this->ip_address ?? '—',
            'summary' => $this->summary,
        ];
    }
}
