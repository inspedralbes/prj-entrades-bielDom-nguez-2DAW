<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Silenci de toasts de fil compartit (event/ticket) per parella usuari–amic.
 * El xat i les notificacions a la llista segueixen arribant; només es suprimeix el toast emergent.
 */
class SocialThreadNotificationMute extends Model
{
    protected $table = 'social_thread_notification_mutes';

    protected $fillable = [
        'user_id',
        'peer_user_id',
    ];

    /**
     * @return BelongsTo<User, SocialThreadNotificationMute>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return BelongsTo<User, SocialThreadNotificationMute>
     */
    public function peer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'peer_user_id');
    }
}
