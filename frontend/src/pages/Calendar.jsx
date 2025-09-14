import { useState, useRef, useEffect } from 'react';
import FullCalendar from '@fullcalendar/react';
import dayGridPlugin from '@fullcalendar/daygrid';
import timeGridPlugin from '@fullcalendar/timegrid';
import interactionPlugin from '@fullcalendar/interaction';
import { motion, AnimatePresence } from 'framer-motion';
import {
  Plus,
  Calendar as CalendarIcon,
  Clock,
  MapPin,
  Users,
  Tag,
  X,
  Edit,
  Trash2,
  ChevronLeft,
  ChevronRight,
  AlertCircle,
  Repeat
} from 'lucide-react';
import { useForm } from 'react-hook-form';
import { format, addDays, parseISO } from 'date-fns';
import { it } from 'date-fns/locale';
import toast from 'react-hot-toast';

/**
 * Componente Calendario con supporto eventi multi-day e drag & drop
 */
const Calendar = () => {
  const calendarRef = useRef(null);
  const [events, setEvents] = useState([
    {
      id: '1',
      title: 'Meeting Team',
      start: new Date(),
      end: addDays(new Date(), 1),
      backgroundColor: '#2563EB',
      extendedProps: {
        description: 'Riunione mensile del team',
        location: 'Sala conferenze',
        attendees: ['Mario Rossi', 'Laura Bianchi'],
        type: 'meeting'
      }
    },
    {
      id: '2',
      title: 'Progetto Alpha - Sprint Review',
      start: addDays(new Date(), 2),
      backgroundColor: '#10B981',
      extendedProps: {
        description: 'Review dello sprint corrente',
        type: 'project'
      }
    },
    {
      id: '3',
      title: 'Task ricorrente',
      start: new Date(),
      backgroundColor: '#F59E0B',
      rrule: {
        freq: 'weekly',
        byweekday: ['mo', 'we', 'fr'],
        dtstart: new Date().toISOString()
      },
      extendedProps: {
        description: 'Task che si ripete ogni lunedì, mercoledì e venerdì',
        type: 'task',
        isRecurring: true
      }
    }
  ]);

  const [showEventModal, setShowEventModal] = useState(false);
  const [selectedEvent, setSelectedEvent] = useState(null);
  const [selectedDates, setSelectedDates] = useState([]);
  const [viewMode, setViewMode] = useState('dayGridMonth');
  const [showSidebar, setShowSidebar] = useState(true);

  const { register, handleSubmit, reset, setValue, watch, formState: { errors } } = useForm();

  const eventTypes = [
    { value: 'meeting', label: 'Riunione', color: '#2563EB' },
    { value: 'task', label: 'Task', color: '#10B981' },
    { value: 'deadline', label: 'Scadenza', color: '#EF4444' },
    { value: 'project', label: 'Progetto', color: '#F59E0B' },
    { value: 'personal', label: 'Personale', color: '#8B5CF6' }
  ];

  // Gestione selezione date multiple non consecutive
  const handleDateSelect = (selectInfo) => {
    if (selectInfo.view.type === 'dayGridMonth') {
      const selectedDate = selectInfo.startStr;

      setSelectedDates(prev => {
        const exists = prev.includes(selectedDate);
        if (exists) {
          return prev.filter(d => d !== selectedDate);
        } else {
          return [...prev, selectedDate];
        }
      });
    } else {
      // Per vista settimana/giorno, apri modal per nuovo evento
      setSelectedEvent(null);
      setValue('start', selectInfo.startStr);
      setValue('end', selectInfo.endStr);
      setShowEventModal(true);
    }
  };

  // Gestione click su evento
  const handleEventClick = (clickInfo) => {
    const event = clickInfo.event;
    setSelectedEvent({
      id: event.id,
      title: event.title,
      start: event.startStr,
      end: event.endStr,
      ...event.extendedProps
    });

    // Popola form con dati evento
    setValue('title', event.title);
    setValue('type', event.extendedProps.type);
    setValue('description', event.extendedProps.description);
    setValue('location', event.extendedProps.location);
    setValue('start', format(event.start, "yyyy-MM-dd'T'HH:mm"));
    setValue('end', event.end ? format(event.end, "yyyy-MM-dd'T'HH:mm") : '');

    setShowEventModal(true);
  };

  // Gestione drag & drop
  const handleEventDrop = (dropInfo) => {
    const { event } = dropInfo;

    setEvents(prev => prev.map(e => {
      if (e.id === event.id) {
        return {
          ...e,
          start: event.start,
          end: event.end
        };
      }
      return e;
    }));

    toast.success('Evento spostato con successo');
  };

  // Gestione resize evento
  const handleEventResize = (resizeInfo) => {
    const { event } = resizeInfo;

    setEvents(prev => prev.map(e => {
      if (e.id === event.id) {
        return {
          ...e,
          start: event.start,
          end: event.end
        };
      }
      return e;
    }));

    toast.success('Durata evento modificata');
  };

  // Salva evento
  const onSubmitEvent = (data) => {
    if (selectedEvent) {
      // Modifica evento esistente
      setEvents(prev => prev.map(e => {
        if (e.id === selectedEvent.id) {
          const eventType = eventTypes.find(t => t.value === data.type);
          return {
            ...e,
            title: data.title,
            start: data.start,
            end: data.end || data.start,
            backgroundColor: eventType.color,
            extendedProps: {
              description: data.description,
              location: data.location,
              type: data.type,
              attendees: data.attendees ? data.attendees.split(',').map(a => a.trim()) : []
            }
          };
        }
        return e;
      }));
      toast.success('Evento modificato con successo');
    } else {
      // Crea nuovo evento
      const eventType = eventTypes.find(t => t.value === data.type);
      const newEvent = {
        id: Date.now().toString(),
        title: data.title,
        start: data.start,
        end: data.end || data.start,
        backgroundColor: eventType.color,
        extendedProps: {
          description: data.description,
          location: data.location,
          type: data.type,
          attendees: data.attendees ? data.attendees.split(',').map(a => a.trim()) : []
        }
      };

      // Se ci sono date selezionate multiple, crea eventi multipli
      if (selectedDates.length > 0) {
        const multiEvents = selectedDates.map((date, index) => ({
          ...newEvent,
          id: `${newEvent.id}-${index}`,
          start: date,
          end: date
        }));
        setEvents(prev => [...prev, ...multiEvents]);
        setSelectedDates([]);
      } else {
        setEvents(prev => [...prev, newEvent]);
      }

      toast.success('Evento creato con successo');
    }

    setShowEventModal(false);
    reset();
  };

  // Elimina evento
  const handleDeleteEvent = () => {
    if (selectedEvent) {
      setEvents(prev => prev.filter(e => e.id !== selectedEvent.id));
      toast.success('Evento eliminato');
      setShowEventModal(false);
      setSelectedEvent(null);
      reset();
    }
  };

  // Cambia vista calendario
  const changeView = (view) => {
    const calendarApi = calendarRef.current.getApi();
    calendarApi.changeView(view);
    setViewMode(view);
  };

  // Navigazione calendario
  const navigateCalendar = (direction) => {
    const calendarApi = calendarRef.current.getApi();
    if (direction === 'prev') {
      calendarApi.prev();
    } else if (direction === 'next') {
      calendarApi.next();
    } else if (direction === 'today') {
      calendarApi.today();
    }
  };

  return (
    <div className="flex h-full">
      {/* Sidebar filtri e info */}
      <AnimatePresence>
        {showSidebar && (
          <motion.div
            initial={{ x: -300 }}
            animate={{ x: 0 }}
            exit={{ x: -300 }}
            className="w-80 border-r border-gray-200 dark:border-gray-700 bg-white dark:bg-dark-card p-6 overflow-y-auto"
          >
            <div className="space-y-6">
              {/* Pulsante nuovo evento */}
              <button
                onClick={() => {
                  setSelectedEvent(null);
                  reset();
                  setShowEventModal(true);
                }}
                className="btn-primary w-full flex items-center justify-center gap-2"
              >
                <Plus className="h-5 w-5" />
                Nuovo Evento
              </button>

              {/* Date selezionate */}
              {selectedDates.length > 0 && (
                <div className="card p-4">
                  <h3 className="text-sm font-semibold text-gray-900 dark:text-white mb-3">
                    Date Selezionate ({selectedDates.length})
                  </h3>
                  <div className="space-y-2 max-h-40 overflow-y-auto">
                    {selectedDates.map(date => (
                      <div key={date} className="flex items-center justify-between text-sm">
                        <span className="text-gray-600 dark:text-gray-400">
                          {format(parseISO(date), 'dd MMM yyyy', { locale: it })}
                        </span>
                        <button
                          onClick={() => setSelectedDates(prev => prev.filter(d => d !== date))}
                          className="text-gray-400 hover:text-error"
                        >
                          <X className="h-4 w-4" />
                        </button>
                      </div>
                    ))}
                  </div>
                  <button
                    onClick={() => {
                      setShowEventModal(true);
                      setSelectedEvent(null);
                      reset();
                    }}
                    className="btn-secondary w-full mt-3"
                  >
                    Crea Evento Multi-Day
                  </button>
                </div>
              )}

              {/* Filtri per tipo */}
              <div>
                <h3 className="text-sm font-semibold text-gray-900 dark:text-white mb-3">
                  Tipi di Evento
                </h3>
                <div className="space-y-2">
                  {eventTypes.map(type => (
                    <label key={type.value} className="flex items-center gap-3 cursor-pointer">
                      <input
                        type="checkbox"
                        defaultChecked
                        className="rounded border-gray-300 text-primary focus:ring-primary"
                      />
                      <div className="flex items-center gap-2 flex-1">
                        <div
                          className="w-3 h-3 rounded-full"
                          style={{ backgroundColor: type.color }}
                        />
                        <span className="text-sm text-gray-700 dark:text-gray-300">
                          {type.label}
                        </span>
                      </div>
                    </label>
                  ))}
                </div>
              </div>

              {/* Mini calendario */}
              <div>
                <h3 className="text-sm font-semibold text-gray-900 dark:text-white mb-3">
                  Navigazione Rapida
                </h3>
                <div className="grid grid-cols-7 gap-1 text-center text-xs">
                  {['L', 'M', 'M', 'G', 'V', 'S', 'D'].map((day, i) => (
                    <div key={i} className="text-gray-500 dark:text-gray-400 py-1">
                      {day}
                    </div>
                  ))}
                  {[...Array(35)].map((_, i) => (
                    <button
                      key={i}
                      className="aspect-square flex items-center justify-center rounded hover:bg-gray-100 dark:hover:bg-gray-700"
                    >
                      {((i % 31) + 1)}
                    </button>
                  ))}
                </div>
              </div>

              {/* Eventi prossimi */}
              <div>
                <h3 className="text-sm font-semibold text-gray-900 dark:text-white mb-3">
                  Prossimi Eventi
                </h3>
                <div className="space-y-2">
                  {events.slice(0, 5).map(event => (
                    <div
                      key={event.id}
                      className="p-3 bg-gray-50 dark:bg-gray-800 rounded-lg cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-700"
                      onClick={() => handleEventClick({ event })}
                    >
                      <div className="flex items-start gap-3">
                        <div
                          className="w-2 h-2 rounded-full mt-1.5"
                          style={{ backgroundColor: event.backgroundColor }}
                        />
                        <div className="flex-1">
                          <p className="text-sm font-medium text-gray-900 dark:text-white">
                            {event.title}
                          </p>
                          <p className="text-xs text-gray-500 dark:text-gray-400 mt-1">
                            {format(new Date(event.start), 'dd MMM HH:mm', { locale: it })}
                          </p>
                        </div>
                      </div>
                    </div>
                  ))}
                </div>
              </div>
            </div>
          </motion.div>
        )}
      </AnimatePresence>

      {/* Calendario principale */}
      <div className="flex-1 flex flex-col">
        {/* Toolbar */}
        <div className="border-b border-gray-200 dark:border-gray-700 bg-white dark:bg-dark-card p-4">
          <div className="flex items-center justify-between">
            <div className="flex items-center gap-2">
              <button
                onClick={() => setShowSidebar(!showSidebar)}
                className="btn-ghost p-2"
              >
                <CalendarIcon className="h-5 w-5" />
              </button>
              <div className="flex items-center gap-1">
                <button
                  onClick={() => navigateCalendar('prev')}
                  className="btn-ghost p-2"
                >
                  <ChevronLeft className="h-5 w-5" />
                </button>
                <button
                  onClick={() => navigateCalendar('today')}
                  className="btn-ghost px-3 py-1"
                >
                  Oggi
                </button>
                <button
                  onClick={() => navigateCalendar('next')}
                  className="btn-ghost p-2"
                >
                  <ChevronRight className="h-5 w-5" />
                </button>
              </div>
            </div>

            <div className="flex items-center gap-2">
              <button
                onClick={() => changeView('dayGridMonth')}
                className={`btn-ghost px-3 py-1 ${viewMode === 'dayGridMonth' ? 'bg-gray-100 dark:bg-gray-800' : ''}`}
              >
                Mese
              </button>
              <button
                onClick={() => changeView('timeGridWeek')}
                className={`btn-ghost px-3 py-1 ${viewMode === 'timeGridWeek' ? 'bg-gray-100 dark:bg-gray-800' : ''}`}
              >
                Settimana
              </button>
              <button
                onClick={() => changeView('timeGridDay')}
                className={`btn-ghost px-3 py-1 ${viewMode === 'timeGridDay' ? 'bg-gray-100 dark:bg-gray-800' : ''}`}
              >
                Giorno
              </button>
            </div>
          </div>
        </div>

        {/* FullCalendar */}
        <div className="flex-1 p-4 bg-white dark:bg-dark-card">
          <FullCalendar
            ref={calendarRef}
            plugins={[dayGridPlugin, timeGridPlugin, interactionPlugin]}
            initialView="dayGridMonth"
            headerToolbar={false}
            events={events}
            editable={true}
            selectable={true}
            selectMirror={true}
            dayMaxEvents={3}
            weekends={true}
            locale="it"
            select={handleDateSelect}
            eventClick={handleEventClick}
            eventDrop={handleEventDrop}
            eventResize={handleEventResize}
            height="100%"
            eventClassNames="cursor-pointer hover:opacity-80 transition-opacity"
            dayCellClassNames="hover:bg-gray-50 dark:hover:bg-gray-800"
          />
        </div>
      </div>

      {/* Modal evento */}
      <AnimatePresence>
        {showEventModal && (
          <motion.div
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            exit={{ opacity: 0 }}
            className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
            onClick={() => setShowEventModal(false)}
          >
            <motion.div
              initial={{ scale: 0.9, opacity: 0 }}
              animate={{ scale: 1, opacity: 1 }}
              exit={{ scale: 0.9, opacity: 0 }}
              onClick={(e) => e.stopPropagation()}
              className="w-full max-w-lg bg-white dark:bg-dark-card rounded-xl shadow-xl"
            >
              <div className="flex items-center justify-between p-6 border-b border-gray-200 dark:border-gray-700">
                <h2 className="text-xl font-semibold text-gray-900 dark:text-white">
                  {selectedEvent ? 'Modifica Evento' : 'Nuovo Evento'}
                </h2>
                <button
                  onClick={() => setShowEventModal(false)}
                  className="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200"
                >
                  <X className="h-6 w-6" />
                </button>
              </div>

              <form onSubmit={handleSubmit(onSubmitEvent)} className="p-6 space-y-4">
                {/* Titolo */}
                <div>
                  <label className="label">Titolo *</label>
                  <input
                    {...register('title', { required: 'Titolo richiesto' })}
                    className={`input ${errors.title ? 'input-error' : ''}`}
                    placeholder="Inserisci titolo evento"
                  />
                  {errors.title && (
                    <p className="mt-1 text-sm text-error flex items-center gap-1">
                      <AlertCircle className="h-4 w-4" />
                      {errors.title.message}
                    </p>
                  )}
                </div>

                {/* Tipo */}
                <div>
                  <label className="label">Tipo</label>
                  <select {...register('type')} className="input">
                    {eventTypes.map(type => (
                      <option key={type.value} value={type.value}>
                        {type.label}
                      </option>
                    ))}
                  </select>
                </div>

                {/* Date e ora */}
                <div className="grid grid-cols-2 gap-4">
                  <div>
                    <label className="label">Inizio *</label>
                    <input
                      {...register('start', { required: 'Data inizio richiesta' })}
                      type="datetime-local"
                      className={`input ${errors.start ? 'input-error' : ''}`}
                    />
                  </div>
                  <div>
                    <label className="label">Fine</label>
                    <input
                      {...register('end')}
                      type="datetime-local"
                      className="input"
                    />
                  </div>
                </div>

                {/* Descrizione */}
                <div>
                  <label className="label">Descrizione</label>
                  <textarea
                    {...register('description')}
                    rows={3}
                    className="input"
                    placeholder="Aggiungi una descrizione..."
                  />
                </div>

                {/* Location */}
                <div>
                  <label className="label">Luogo</label>
                  <div className="relative">
                    <MapPin className="absolute left-3 top-1/2 -translate-y-1/2 h-5 w-5 text-gray-400" />
                    <input
                      {...register('location')}
                      className="input pl-10"
                      placeholder="Aggiungi luogo"
                    />
                  </div>
                </div>

                {/* Partecipanti */}
                <div>
                  <label className="label">Partecipanti</label>
                  <div className="relative">
                    <Users className="absolute left-3 top-1/2 -translate-y-1/2 h-5 w-5 text-gray-400" />
                    <input
                      {...register('attendees')}
                      className="input pl-10"
                      placeholder="Email partecipanti (separati da virgola)"
                    />
                  </div>
                </div>

                {/* Azioni */}
                <div className="flex items-center justify-between pt-4">
                  <div>
                    {selectedEvent && (
                      <button
                        type="button"
                        onClick={handleDeleteEvent}
                        className="btn-error"
                      >
                        <Trash2 className="h-4 w-4 mr-2" />
                        Elimina
                      </button>
                    )}
                  </div>
                  <div className="flex items-center gap-3">
                    <button
                      type="button"
                      onClick={() => setShowEventModal(false)}
                      className="btn-ghost"
                    >
                      Annulla
                    </button>
                    <button type="submit" className="btn-primary">
                      {selectedEvent ? 'Salva Modifiche' : 'Crea Evento'}
                    </button>
                  </div>
                </div>
              </form>
            </motion.div>
          </motion.div>
        )}
      </AnimatePresence>
    </div>
  );
};

export default Calendar;