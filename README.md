# michaeljmeadows/has-histories

A simple trait to aid Eloquent model version history logging.

## Installation 

You can install the package via composer:

```
composer require michaeljmeadows/has-histories
```

## Usage

Add a migration to store your model histories. This should contain all the same fields as your main model table as well as a reference ID to the original model. Your model's history table can be named however your like, but the default convention would be `models` -> `model_histories`. We recommend modifying your migrations as shown in this modification of the Laravel Jetstream User migration:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $tables = [
        'users',
        'user_histories',
    ];

    public function up(): void
    {
        foreach ($this->tables as $tableName) {
            Schema::create($tableName, function (Blueprint $table) use ($tableName) {
                $isHistoriesTable = str($tableName)->contains('histories');

                $table->id();
                if ($isHistoriesTable) {
                    $table->foreignId('user_id');
                }
                $table->string('name');
                $table->string('email');
                $table->timestamp('email_verified_at')->nullable();
                $table->string('password');
                $table->rememberToken();
                $table->foreignId('current_team_id')->nullable();
                $table->string('profile_photo_path', 2048)->nullable();
                $table->timestamps();

                if (! $isHistoriesTable) {
                    $table->unique('email');
                }
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $tableName) {
            Schema::dropIfExists($tableName);
        }
    }
};
```

Once the migration has been added, you can simply include the trait in your model's definition:

```php
<?php

namespace App\Models;

use michaeljmeadows\Traits\HasHistories;
use Illuminate\Database\Eloquent\Model;

class NewModel extends Model
{
    use HasHistories;
	
```

Version history can then be logged by calling the model's `saveHistory()` method when changes are being saved. We recommend a model observer, but Events are also a sensible alternative:

```php
<?php

namespace App\Observers;

use App\Models\NewModel;
use Illuminate\Support\Facades\Auth;

class NewModelObserver
{
    public function creating(NewModel $newModel): void
    {
        $newModel->saveHistory();
    }
	
    public function updating(NewModel $newModel): void
    {
        $newModel->saveHistory();
    }
	
    public function deleting(NewModel $newModel): void
    {
        $newModel->saveHistory();
    }
```

### Restoring Models

Models can be restored using one of three methods:

```php
$newModel->restorePrevious();
$newModel->restorePreviousIteration(3);
$newModel->restoreBeforeDate('2022-01-01');
```

These functions return true on success and false if a historic state was not found.

`restorePrevious()` will restore a model to its previous state in the histories table.

`restorePreviousIteration(int $index)` will restore a model to a state using zero-based numbering. (i.e. `restorePreviousIteration(0)` is the same as `restorePrevious()`).

`restoreBeforeDate(DateTimeInterface|string $date)` will restore a model to the most recent state before the given `$date` value using the `updated_at` and `created_at` fields.

#### Restoration Notes
- When restored, a copy of the current model is also saved to the histories table.
- HasHistories uses the history table `id` field to assess the most recent state, as multiple restorations can lead to apparent duplicates appearing in the histories table.

### Customising Behaviour

#### Ignored Fields
Not every change to a model's attributes is worth logging. In the Jetstream User example above, it may be that you'd rather ignore changes to `email_verified_at`. In this case, you can add a protected array attribute `$ignoredFields` to your model specifying which attributes you're not interested in:

```php
<?php

namespace App\Models;

use michaeljmeadows\Traits\HasHistories;
use Illuminate\Database\Eloquent\Model;

class User extends Model
{
    use HasHistories;
	
    protected array $ignoredFields = [
        'email_verified_at',
    ];
```

If you choose to ignore fields these should not be included in the history table migration.

#### Histories Table Name
By default, HasHistories uses the naming convention `models` -> `model_histories` when determining the history table name, but if for whatever reason that doesn't work for you, you can specify a different history table name by adding a protected string attribute `$historiesTable` to your model:

```php
<?php

namespace App\Models;

use michaeljmeadows\Traits\HasHistories;
use Illuminate\Database\Eloquent\Model;

class User extends Model
{
    use HasHistories;
	
    protected string $historyTable = 'user_logging'; // Instead of 'user_histories'.
```

#### Histories Table Model Reference
By default, HasHistories associates an entry in the history table with a model with an attribute in the form `models` -> `model_id`, but if for whatever reason that doesn't work for you, you can specify a different field by adding a protected string attribute `$historiesModelIdReference`:

```php
<?php

namespace App\Models;

use michaeljmeadows\Traits\HasHistories;
use Illuminate\Database\Eloquent\Model;

class User extends Model
{
    use HasHistories;
	
    protected string $historiesModelIdReference = 'user_model_id'; // Instead of 'user_id'.
```

#### Histories Table Connection
By default, HasHistories expects that the history table will use the same connection as the model to which it is applied. Occasionally you may want to specify a different connection for your history table. This can be done with an optional parameter in your `saveHistory` method call:

```php
<?php

namespace App\Observers;

use App\Models\NewModel;
use Illuminate\Support\Facades\Auth;

class NewModelObserver
{
    public function creating(NewModel $newModel): void
    {
        $newModel->saveHistory('sqlite');
    }
```

When restoring models, the same connection parameter can be added at the end of each method call:

```php
$newModel->restorePrevious('sqlite');
$newModel->restorePreviousIteration(3, 'sqlite');
$newModel->restoreBeforeDate('2022-01-01', 'sqlite');
```