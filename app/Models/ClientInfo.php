<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClientInfo extends Model
{
    use HasFactory;
    protected $fillable=[
        'url',
        'image'
    ];

    /**
    * Format the date when serializing the model.
    *
   * @param \DateTimeInterface $date
   * @return string
   */
  protected function serializeDate(\DateTimeInterface $date)
  {
      return $date->format('Y-m-d');
  }
}
