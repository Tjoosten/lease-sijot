<?php

namespace App\Http\Controllers\Monitor;

use App\Models\City;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use App\Http\Requests\Monitor\NoteValidator;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\Support\Renderable;
use App\Models\Notes;

/**
 * Class NoteController
 *
 * @package App\Http\Controller\Monitor
 */
class NoteController extends Controller
{
    /**
     * NoteController constructor
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware(['auth', 'role:admin', 'forbid-banned-user']);
    }

    /**
     * Display all the notes from the given city.
     *
     * @param  Request  $request    The form request instance that holds all the request information.
     * @param  City     $city       The database entity from the given city
     * @return Renderable
     */
    public function index(Request $request, City $city): Renderable
    {
        $notes = $city->postal->notes(); 

        switch ($request->filter) {
            case 'gebruiker': $notes = $notes->whereAuthorId(auth()->user()->id);
        }

        return view('monitor.notes.index', ['city' => $city, 'notes' => $notes->simplePaginate()]);
    }

    /**
     * Method for search specific notes that are attached to the city.
     * 
     * @param  Request  $request    The form request information instance
     * @param  City     $city       Database entity from the given city. 
     * @return Renderable 
     */
    public function search(Request $request, City $city): Renderable
    {
        $notes = Notes::where('titel', 'LIKE', "%{$request->term}%")
            ->orWhere('beschrijving', 'LIKE', "%{$request->term}%")
            ->wherePostalId($city->id)
            ->simplePaginate();

        return view('monitor.notes.index', compact('city', 'notes')); 
    }

    /**
     * Method for displaying a note in the application. 
     * 
     * @param  Notes $note The database entity from the given note. 
     * @return Renderable 
     */
    public function show(Notes $note): Renderable
    {
        return view('monitor.notes.show', compact('note'));
    } 

    /**
     * Display view for creating a new note in the application.
     *
     * @param  City $city The database entity from the given city.
     * @return Renderable
     */
    public function create(City $city): Renderable
    {
        return view('monitor.notes.create', compact('city'));
    }

    /**
     * Method for storing a note for an city in the application.
     *
     * @param  NoteValidator    $input The form request class that handles the validation. 
     * @param  City             $city  The entity from the given city in the application.
     * @return RedirectResponse
     */
    public function store(NoteValidator $input, City $city): RedirectResponse
    {
        $note = (new Notes)->create($input->all());
        $authUser = auth()->user();

        if ($city->postal->notes()->save($note)) {
            $note->city()->associate($city)->save();       // Associate a city the note. 
            $note->author()->associate($authUser)->save(); // Associate the authenticated user to the note. 
            $authUser->logActivity($note, 'Notities', "Heeft een notitie toegvoegd voor de stad {$city->naam}.");
        }

        return redirect()->route('note.show', $note);
    }

    /**
     * Method for deleting a note in the application.
     *
     * @throws \Illuminate\Auth\Access\AuthorizationException
     *
     * @param  Notes $note The database entity from the given note.
     * @return RedirectResponse
     */
    public function destroy(Notes $note): RedirectResponse
    {
        $this->authorize('delete', $note); // Check if the user to permitted. 

        if ($note->delete()) {
            flash('De notitie is verwijderd uit de monitor.')->success();
            auth()->user()->logActivity($note, 'Notities', "Heeft een notitie verwijderd in de applicatie.");
        }

        return redirect()->route('monitor.notes', $note->city); // HTTP 302: Redirect user to the previous page.
    }
}
