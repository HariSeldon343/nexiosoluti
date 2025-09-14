import { useState } from 'react';
import { DragDropContext, Droppable, Draggable } from 'react-beautiful-dnd';
import { motion } from 'framer-motion';
import {
  Plus,
  Calendar,
  Clock,
  User,
  Tag,
  Paperclip,
  MessageSquare,
  MoreHorizontal,
  CheckSquare,
  List,
  BarChart3
} from 'lucide-react';

/**
 * Task Manager con vista Kanban
 */
const TaskManager = () => {
  const [view, setView] = useState('kanban'); // kanban | list | timeline
  const [columns, setColumns] = useState({
    todo: {
      id: 'todo',
      title: 'Da fare',
      color: 'bg-gray-500',
      tasks: [
        {
          id: '1',
          title: 'Implementare autenticazione 2FA',
          description: 'Aggiungere supporto per Google Authenticator',
          priority: 'high',
          assignees: ['Mario Rossi'],
          dueDate: '2024-03-20',
          tags: ['backend', 'security'],
          comments: 3,
          attachments: 2
        },
        {
          id: '2',
          title: 'Ottimizzare query database',
          description: 'Ridurre tempo di caricamento dashboard',
          priority: 'medium',
          assignees: ['Laura Bianchi'],
          dueDate: '2024-03-22',
          tags: ['performance'],
          comments: 1,
          attachments: 0
        }
      ]
    },
    inProgress: {
      id: 'inProgress',
      title: 'In corso',
      color: 'bg-primary',
      tasks: [
        {
          id: '3',
          title: 'Sviluppo API REST',
          description: 'Endpoint per gestione documenti',
          priority: 'high',
          assignees: ['Giuseppe Verdi', 'Anna Neri'],
          dueDate: '2024-03-18',
          tags: ['api', 'backend'],
          comments: 5,
          attachments: 3
        }
      ]
    },
    review: {
      id: 'review',
      title: 'In revisione',
      color: 'bg-warning',
      tasks: [
        {
          id: '4',
          title: 'Testing componenti React',
          description: 'Unit test per dashboard',
          priority: 'low',
          assignees: ['Paolo Bianchi'],
          dueDate: '2024-03-19',
          tags: ['testing', 'frontend'],
          comments: 2,
          attachments: 1
        }
      ]
    },
    done: {
      id: 'done',
      title: 'Completato',
      color: 'bg-success',
      tasks: [
        {
          id: '5',
          title: 'Setup CI/CD pipeline',
          description: 'Configurazione GitHub Actions',
          priority: 'medium',
          assignees: ['Mario Rossi'],
          dueDate: '2024-03-15',
          tags: ['devops'],
          comments: 8,
          attachments: 4
        }
      ]
    }
  });

  const onDragEnd = (result) => {
    if (!result.destination) return;

    const { source, destination } = result;

    if (source.droppableId !== destination.droppableId) {
      const sourceColumn = columns[source.droppableId];
      const destColumn = columns[destination.droppableId];
      const sourceItems = [...sourceColumn.tasks];
      const destItems = [...destColumn.tasks];
      const [removed] = sourceItems.splice(source.index, 1);
      destItems.splice(destination.index, 0, removed);

      setColumns({
        ...columns,
        [source.droppableId]: {
          ...sourceColumn,
          tasks: sourceItems
        },
        [destination.droppableId]: {
          ...destColumn,
          tasks: destItems
        }
      });
    } else {
      const column = columns[source.droppableId];
      const copiedItems = [...column.tasks];
      const [removed] = copiedItems.splice(source.index, 1);
      copiedItems.splice(destination.index, 0, removed);

      setColumns({
        ...columns,
        [source.droppableId]: {
          ...column,
          tasks: copiedItems
        }
      });
    }
  };

  const getPriorityColor = (priority) => {
    switch (priority) {
      case 'high': return 'text-error bg-error-100 dark:bg-error-900/20';
      case 'medium': return 'text-warning bg-warning-100 dark:bg-warning-900/20';
      case 'low': return 'text-success bg-success-100 dark:bg-success-900/20';
      default: return 'text-gray-500 bg-gray-100 dark:bg-gray-800';
    }
  };

  return (
    <div className="h-full flex flex-col">
      {/* Header */}
      <div className="border-b border-gray-200 dark:border-gray-700 bg-white dark:bg-dark-card p-4">
        <div className="flex items-center justify-between">
          <h1 className="text-xl font-semibold text-gray-900 dark:text-white">
            Task Manager
          </h1>
          <div className="flex items-center gap-2">
            <div className="flex items-center gap-1 bg-gray-100 dark:bg-gray-800 rounded-lg p-1">
              <button
                onClick={() => setView('kanban')}
                className={`px-3 py-1.5 rounded text-sm font-medium transition-colors ${
                  view === 'kanban'
                    ? 'bg-white dark:bg-gray-700 text-primary shadow-sm'
                    : 'text-gray-600 dark:text-gray-400'
                }`}
              >
                <CheckSquare className="h-4 w-4 inline mr-1" />
                Kanban
              </button>
              <button
                onClick={() => setView('list')}
                className={`px-3 py-1.5 rounded text-sm font-medium transition-colors ${
                  view === 'list'
                    ? 'bg-white dark:bg-gray-700 text-primary shadow-sm'
                    : 'text-gray-600 dark:text-gray-400'
                }`}
              >
                <List className="h-4 w-4 inline mr-1" />
                Lista
              </button>
              <button
                onClick={() => setView('timeline')}
                className={`px-3 py-1.5 rounded text-sm font-medium transition-colors ${
                  view === 'timeline'
                    ? 'bg-white dark:bg-gray-700 text-primary shadow-sm'
                    : 'text-gray-600 dark:text-gray-400'
                }`}
              >
                <BarChart3 className="h-4 w-4 inline mr-1" />
                Timeline
              </button>
            </div>
            <button className="btn-primary flex items-center gap-2">
              <Plus className="h-4 w-4" />
              Nuovo Task
            </button>
          </div>
        </div>
      </div>

      {/* Kanban Board */}
      {view === 'kanban' && (
        <div className="flex-1 overflow-x-auto p-6">
          <DragDropContext onDragEnd={onDragEnd}>
            <div className="flex gap-4 h-full">
              {Object.entries(columns).map(([columnId, column]) => (
                <div key={columnId} className="flex-shrink-0 w-80">
                  <div className="bg-gray-50 dark:bg-gray-800 rounded-lg p-4 h-full">
                    {/* Column header */}
                    <div className="flex items-center justify-between mb-4">
                      <div className="flex items-center gap-2">
                        <div className={`w-3 h-3 rounded-full ${column.color}`} />
                        <h3 className="font-semibold text-gray-900 dark:text-white">
                          {column.title}
                        </h3>
                        <span className="text-sm text-gray-500 dark:text-gray-400">
                          {column.tasks.length}
                        </span>
                      </div>
                      <button className="p-1 hover:bg-gray-200 dark:hover:bg-gray-700 rounded">
                        <Plus className="h-4 w-4 text-gray-500" />
                      </button>
                    </div>

                    {/* Tasks */}
                    <Droppable droppableId={columnId}>
                      {(provided, snapshot) => (
                        <div
                          ref={provided.innerRef}
                          {...provided.droppableProps}
                          className={`space-y-2 min-h-[200px] ${
                            snapshot.isDraggingOver ? 'bg-primary-50 dark:bg-primary-900/10 rounded' : ''
                          }`}
                        >
                          {column.tasks.map((task, index) => (
                            <Draggable key={task.id} draggableId={task.id} index={index}>
                              {(provided, snapshot) => (
                                <motion.div
                                  ref={provided.innerRef}
                                  {...provided.draggableProps}
                                  {...provided.dragHandleProps}
                                  whileHover={{ scale: 1.02 }}
                                  className={`bg-white dark:bg-dark-card p-4 rounded-lg border border-gray-200 dark:border-gray-700 shadow-sm cursor-move ${
                                    snapshot.isDragging ? 'shadow-lg ring-2 ring-primary' : ''
                                  }`}
                                >
                                  {/* Priority badge */}
                                  <div className="flex items-center justify-between mb-2">
                                    <span className={`text-xs px-2 py-1 rounded-full font-medium ${getPriorityColor(task.priority)}`}>
                                      {task.priority === 'high' ? 'Alta' : task.priority === 'medium' ? 'Media' : 'Bassa'}
                                    </span>
                                    <button className="p-1 hover:bg-gray-100 dark:hover:bg-gray-800 rounded">
                                      <MoreHorizontal className="h-4 w-4 text-gray-400" />
                                    </button>
                                  </div>

                                  {/* Task title */}
                                  <h4 className="font-medium text-gray-900 dark:text-white mb-2">
                                    {task.title}
                                  </h4>

                                  {/* Task description */}
                                  <p className="text-sm text-gray-600 dark:text-gray-400 mb-3 line-clamp-2">
                                    {task.description}
                                  </p>

                                  {/* Tags */}
                                  <div className="flex flex-wrap gap-1 mb-3">
                                    {task.tags.map((tag, i) => (
                                      <span key={i} className="text-xs px-2 py-1 bg-gray-100 dark:bg-gray-800 rounded text-gray-600 dark:text-gray-400">
                                        {tag}
                                      </span>
                                    ))}
                                  </div>

                                  {/* Footer */}
                                  <div className="flex items-center justify-between text-xs text-gray-500 dark:text-gray-400">
                                    <div className="flex items-center gap-3">
                                      <div className="flex items-center gap-1">
                                        <Calendar className="h-3 w-3" />
                                        {task.dueDate}
                                      </div>
                                      {task.comments > 0 && (
                                        <div className="flex items-center gap-1">
                                          <MessageSquare className="h-3 w-3" />
                                          {task.comments}
                                        </div>
                                      )}
                                      {task.attachments > 0 && (
                                        <div className="flex items-center gap-1">
                                          <Paperclip className="h-3 w-3" />
                                          {task.attachments}
                                        </div>
                                      )}
                                    </div>
                                    <div className="flex -space-x-2">
                                      {task.assignees.slice(0, 2).map((assignee, i) => (
                                        <div key={i} className="w-6 h-6 rounded-full bg-gray-300 dark:bg-gray-600 border-2 border-white dark:border-dark-card flex items-center justify-center">
                                          <span className="text-xs font-medium text-white">
                                            {assignee.charAt(0)}
                                          </span>
                                        </div>
                                      ))}
                                      {task.assignees.length > 2 && (
                                        <div className="w-6 h-6 rounded-full bg-gray-400 dark:bg-gray-500 border-2 border-white dark:border-dark-card flex items-center justify-center">
                                          <span className="text-xs font-medium text-white">
                                            +{task.assignees.length - 2}
                                          </span>
                                        </div>
                                      )}
                                    </div>
                                  </div>
                                </motion.div>
                              )}
                            </Draggable>
                          ))}
                          {provided.placeholder}
                        </div>
                      )}
                    </Droppable>
                  </div>
                </div>
              ))}
            </div>
          </DragDropContext>
        </div>
      )}

      {/* List view placeholder */}
      {view === 'list' && (
        <div className="flex-1 p-6">
          <div className="card p-8 text-center">
            <List className="h-12 w-12 text-gray-400 mx-auto mb-4" />
            <p className="text-gray-500 dark:text-gray-400">Vista lista in costruzione</p>
          </div>
        </div>
      )}

      {/* Timeline view placeholder */}
      {view === 'timeline' && (
        <div className="flex-1 p-6">
          <div className="card p-8 text-center">
            <BarChart3 className="h-12 w-12 text-gray-400 mx-auto mb-4" />
            <p className="text-gray-500 dark:text-gray-400">Vista timeline in costruzione</p>
          </div>
        </div>
      )}
    </div>
  );
};

export default TaskManager;