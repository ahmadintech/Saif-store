<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class AddPaystackToPaymentMethodEnum extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // For MySQL, we need to modify the enum
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE `orders` MODIFY `payment_method` ENUM('cod', 'paypal', 'paystack') DEFAULT 'cod'");
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE `orders` MODIFY `payment_method` ENUM('cod', 'paypal') DEFAULT 'cod'");
        }
    }
}
