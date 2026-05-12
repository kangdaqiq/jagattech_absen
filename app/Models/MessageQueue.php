<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MessageQueue extends Model
{
    protected $table = 'message_queues';
    public $timestamps = true;
    protected $fillable = ['school_id', 'phone_number', 'message', 'status', 'attempts', 'last_error', 'created_at', 'updated_at'];
    
    protected static function booted()
    {
        static::creating(function ($messageQueue) {
            if ($messageQueue->school_id && !empty($messageQueue->message)) {
                $school = \App\Models\School::find($messageQueue->school_id);
                if ($school && !empty($school->name)) {
                    $signature = "*" . trim($school->name) . "*";
                    if (!str_contains($messageQueue->message, $signature)) {
                        $messageQueue->message = rtrim($messageQueue->message) . "\n\n" . $signature;
                    }
                }
            }
        });
    }
    
    /**
     * Mutator to automatically format phone number into WhatsApp JID.
     */
    public function setPhoneNumberAttribute($value)
    {
        if (empty($value)) {
            $this->attributes['phone_number'] = $value;
            return;
        }

        // If it already contains '@', assume it is already a JID (e.g. @s.whatsapp.net or @g.us)
        // Since we are changing to pure numeric format, we will STRIP @s.whatsapp.net if it exists
        if (str_ends_with($value, '@s.whatsapp.net')) {
            $value = str_replace('@s.whatsapp.net', '', $value);
        }
        if (str_contains($value, '@')) {
            $this->attributes['phone_number'] = $value;
            return;
        }

        // Clean non-numeric characters
        $phone = preg_replace('/[^0-9]/', '', $value);

        if (empty($phone)) {
            $this->attributes['phone_number'] = $value;
            return;
        }

        // Standardize Indonesian format: 08xx -> 628xx
        if (str_starts_with($phone, '0')) {
            $phone = '62' . substr($phone, 1);
        } elseif (!str_starts_with($phone, '62')) {
            // Assume it is already without prefix if not starting with 62 or 0
            $phone = '62' . $phone;
        }

        // Append WhatsApp domain
        // $this->attributes['phone_number'] = $phone . '@s.whatsapp.net';
        $this->attributes['phone_number'] = $phone;
    }
}
