<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Http\File;
use Illuminate\Support\Facades\Storage;

/**
 * The real Titik Nol Coffee menu (from the menu board), with photos cut from
 * that board in database/seeders/images/menu.
 *
 * Safe to run again on a live database: products are matched by name, prices
 * and descriptions follow this list, and a photo is only added when the
 * product has none, so images uploaded by the owner are never replaced.
 *
 *   php artisan db:seed --class=TitikNolMenuSeeder
 */
class TitikNolMenuSeeder extends Seeder
{
    /** @var array<string, int> category => sort order */
    public const CATEGORIES = ['Kopi' => 1, 'Non-Kopi' => 2];

    /** Categories from earlier versions of this seeder, removed once empty. */
    public const RETIRED_CATEGORIES = ['Teh & Segar'];

    /** [category, name, price, description] */
    public const MENU = [
        ['Kopi', 'Kopi Sea Salt Cream', 12000, 'Kopi susu dengan krim sea salt yang gurih.'],
        ['Kopi', 'Kopi Butterscotch', 10000, 'Kopi susu dengan sirup butterscotch.'],
        ['Kopi', 'Kopi Pandan', 10000, 'Kopi susu dengan aroma pandan.'],
        ['Kopi', 'Kopi Pistachio', 10000, 'Kopi susu dengan rasa pistachio.'],
        ['Kopi', 'Kopi Caramel', 10000, 'Kopi susu dengan saus karamel.'],
        ['Kopi', 'Vanilla Latte', 10000, 'Latte dengan sirup vanila.'],
        ['Kopi', 'Kopi Milo', 10000, 'Perpaduan kopi susu dan Milo.'],
        ['Kopi', 'Moccacino', 10000, 'Kopi, susu, dan cokelat.'],
        ['Kopi', 'Kopi Gula Aren', 9000, 'Kopi susu dengan gula aren.'],
        ['Kopi', 'Kopi Latte', 9000, 'Kopi dan susu. Menu baru.'],
        ['Kopi', 'Americano', 5000, 'Kopi hitam tanpa gula.'],
        ['Kopi', 'Americano Peach', 10000, 'Americano dengan rasa peach. Menu baru.'],
        ['Kopi', 'Mont Blanc', 12000, 'Menu baru racikan Titik Nol.'],
        ['Non-Kopi', 'Red Velvet Latte', 10000, 'Minuman susu rasa red velvet.'],
        ['Non-Kopi', 'Matcha Latte', 10000, 'Matcha dengan susu.'],
        ['Non-Kopi', 'Taro Latte', 10000, 'Minuman susu rasa taro.'],
        ['Non-Kopi', 'Choco Latte', 10000, 'Cokelat dengan susu.'],
        ['Non-Kopi', 'Lemonade', 5000, 'Minuman lemon yang segar.'],
        ['Non-Kopi', 'Lemontea', 5000, 'Teh dengan perasan lemon.'],
        ['Non-Kopi', 'Es Teh Solo', 4000, 'Es teh manis khas Solo.'],
    ];

    public function run(): void
    {
        $categories = collect(self::CATEGORIES)->map(fn (int $order, string $name) => Category::firstOrCreate(
            ['name' => $name],
            ['sort_order' => $order, 'is_active' => true],
        ));

        foreach (self::MENU as [$category, $name, $price, $description]) {
            $product = Product::withTrashed()->firstOrNew(['name' => $name]);
            $product->fill([
                'category_id' => $categories[$category]->id,
                'price' => $price,
                'description' => $description,
            ]);

            if (! $product->exists) {
                $product->is_available = true;
            }

            if (! $product->image_path || ! Storage::disk('public')->exists($product->image_path)) {
                $product->image_path = $this->storeImage($name);
            }

            $product->save();

            if ($product->trashed()) {
                $product->restore();
            }
        }

        // Only an empty retired category is removed; one the owner filled
        // with their own products stays.
        Category::whereIn('name', self::RETIRED_CATEGORIES)
            ->whereDoesntHave('products', fn ($q) => $q->withTrashed())
            ->delete();
    }

    /**
     * Copy the bundled photo to the public disk. Returns null when the menu
     * item has no bundled photo.
     */
    private function storeImage(string $name): ?string
    {
        $slug = str($name)->slug()->value();
        $source = database_path("seeders/images/menu/{$slug}.jpg");

        if (! is_file($source)) {
            return null;
        }

        return Storage::disk('public')->putFileAs('products', new File($source), "menu-{$slug}.jpg");
    }
}
