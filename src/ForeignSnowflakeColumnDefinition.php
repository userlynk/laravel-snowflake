<?php

namespace Userlynk\Snowflake;

use Illuminate\Database\Schema\ForeignIdColumnDefinition;

class ForeignSnowflakeColumnDefinition extends ForeignIdColumnDefinition
{
    /**
     * Create a foreign key constraint on this column referencing the "sid" column of the conventionally related table.
     *
     * @param  string|null  $table
     * @param  string  $column
     * @return \Illuminate\Database\Schema\ForeignKeyDefinition
     */
    public function constrained($table = null, $column = 'sid')
    {
        return parent::constrained($table, $column);
    }
}
