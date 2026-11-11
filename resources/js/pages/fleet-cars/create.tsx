import { type BreadcrumbItem } from '@/types';
import { TruckIcon } from '@heroicons/react/24/outline';
import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, CarFront, CheckCircle2, CircleHelp, LoaderCircle } from 'lucide-react';
import { type FormEvent, useRef, useState } from 'react';

import InputError from '@/components/input-error';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import { ApiFormError, submitJsonForm } from '@/lib/submit-json-form';

type CarModelOption = {
    id: number;
    model_name: string;
    daily_rate: string;
};

type FleetCarOption = {
    id: number;
    vehicleVin: string;
    carModel: string;
    carModelId: number;
};

type FleetCarEditForm = {
    vehicleVin: string;
    carModelId: string;
};

type CarModelEditForm = {
    modelName: string;
    dailyRate: string;
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Fleet cars', href: '/fleet-cars' },
    { title: 'Add vehicle', href: '/fleet-cars/create' },
];

const formatCurrency = (value: string | number) => new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(Number(value));

export default function CreateFleetCar({ carModels, fleetCars }: { carModels: CarModelOption[]; fleetCars: FleetCarOption[] }) {
    const [vehicleVin, setVehicleVin] = useState('');
    const [carModelId, setCarModelId] = useState('');
    const vinInputRef = useRef<HTMLInputElement>(null);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [processing, setProcessing] = useState(false);
    const [success, setSuccess] = useState('');
    const [successTitle, setSuccessTitle] = useState('Vehicle added');
    const [formError, setFormError] = useState('');
    const [editingFleetCar, setEditingFleetCar] = useState<FleetCarOption | null>(null);
    const [editForm, setEditForm] = useState<FleetCarEditForm>({ vehicleVin: '', carModelId: '' });
    const [editErrors, setEditErrors] = useState<Record<string, string>>({});
    const [editFormError, setEditFormError] = useState('');
    const [editProcessing, setEditProcessing] = useState(false);
    const [editingCarModel, setEditingCarModel] = useState<CarModelOption | null>(null);
    const [carModelForm, setCarModelForm] = useState<CarModelEditForm>({ modelName: '', dailyRate: '' });
    const [carModelErrors, setCarModelErrors] = useState<Record<string, string>>({});
    const [carModelFormError, setCarModelFormError] = useState('');
    const [carModelProcessing, setCarModelProcessing] = useState(false);
    const [addingCarModel, setAddingCarModel] = useState(false);
    const [newCarModelForm, setNewCarModelForm] = useState<CarModelEditForm>({ modelName: '', dailyRate: '' });
    const [newCarModelErrors, setNewCarModelErrors] = useState<Record<string, string>>({});
    const [newCarModelFormError, setNewCarModelFormError] = useState('');
    const [newCarModelProcessing, setNewCarModelProcessing] = useState(false);

    const selectedModel = carModels.find((model) => String(model.id) === carModelId);

    async function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        setErrors({});
        setFormError('');
        setSuccess('');
        setSuccessTitle('Vehicle added');
        setProcessing(true);

        try {
            const result = await submitJsonForm(route('fleet-cars.store'), {
                vehicle_vin: vehicleVin.trim(),
                car_model_id: carModelId,
            });
            setSuccess(result.message ?? 'Vehicle added to your fleet.');
            setVehicleVin('');
            setCarModelId('');
            router.reload({ only: ['fleetCars'] });
        } catch (error) {
            if (error instanceof ApiFormError) {
                setErrors(error.errors);
                setFormError(Object.keys(error.errors).length === 0 ? error.message : '');
            } else {
                setFormError(error instanceof Error ? error.message : 'Unable to save your changes. Please try again.');
            }
        } finally {
            setProcessing(false);
        }
    }

    function openFleetCarEditor(fleetCar: FleetCarOption) {
        setEditingFleetCar(fleetCar);
        setEditForm({ vehicleVin: fleetCar.vehicleVin, carModelId: String(fleetCar.carModelId) });
        setEditErrors({});
        setEditFormError('');
    }

    async function saveFleetCar(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        if (!editingFleetCar) return;

        setEditErrors({});
        setEditFormError('');
        setEditProcessing(true);
        try {
            const result = await submitJsonForm(
                route('fleet-cars.update', editingFleetCar.id),
                {
                    vehicle_vin: editForm.vehicleVin.trim(),
                    car_model_id: editForm.carModelId,
                },
                'PUT',
            );
            setSuccessTitle('Vehicle updated');
            setSuccess(result.message ?? 'Fleet vehicle updated successfully.');
            setEditingFleetCar(null);
            router.reload({ only: ['fleetCars'] });
        } catch (error) {
            if (error instanceof ApiFormError) {
                setEditErrors(error.errors);
                setEditFormError(Object.keys(error.errors).length === 0 ? error.message : '');
            } else {
                setEditFormError(error instanceof Error ? error.message : 'Unable to save the vehicle. Please try again.');
            }
        } finally {
            setEditProcessing(false);
        }
    }

    function openCarModelEditor(carModel: CarModelOption) {
        setEditingCarModel(carModel);
        setCarModelForm({ modelName: carModel.model_name, dailyRate: carModel.daily_rate });
        setCarModelErrors({});
        setCarModelFormError('');
    }

    async function saveCarModel(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        if (!editingCarModel) return;

        setCarModelErrors({});
        setCarModelFormError('');
        setCarModelProcessing(true);
        try {
            const result = await submitJsonForm(
                route('car-models.update', editingCarModel.id),
                {
                    model_name: carModelForm.modelName.trim(),
                    daily_rate: carModelForm.dailyRate,
                },
                'PUT',
            );
            setSuccessTitle('Car model updated');
            setSuccess(result.message ?? 'Car model updated successfully.');
            setEditingCarModel(null);
            router.reload({ only: ['carModels', 'fleetCars'] });
        } catch (error) {
            if (error instanceof ApiFormError) {
                setCarModelErrors(error.errors);
                setCarModelFormError(Object.keys(error.errors).length === 0 ? error.message : '');
            } else {
                setCarModelFormError(error instanceof Error ? error.message : 'Unable to save the car model. Please try again.');
            }
        } finally {
            setCarModelProcessing(false);
        }
    }

    async function createCarModel(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        setNewCarModelErrors({});
        setNewCarModelFormError('');
        setNewCarModelProcessing(true);
        try {
            const result = await submitJsonForm(route('car-models.store'), {
                model_name: newCarModelForm.modelName.trim(),
                daily_rate: newCarModelForm.dailyRate,
            });
            setSuccessTitle('Car model added');
            setSuccess(result.message ?? 'Car model created successfully.');
            setNewCarModelForm({ modelName: '', dailyRate: '' });
            setAddingCarModel(false);
            router.reload({ only: ['carModels'] });
        } catch (error) {
            if (error instanceof ApiFormError) {
                setNewCarModelErrors(error.errors);
                setNewCarModelFormError(Object.keys(error.errors).length === 0 ? error.message : '');
            } else {
                setNewCarModelFormError(error instanceof Error ? error.message : 'Unable to add the car model. Please try again.');
            }
        } finally {
            setNewCarModelProcessing(false);
        }
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Add fleet car" />
            <main className="mx-auto w-full max-w-6xl space-y-8 p-4 md:p-8">
                <Link
                    href="/dashboard"
                    className="text-muted-foreground hover:text-foreground inline-flex items-center gap-2 text-sm transition-colors"
                >
                    <ArrowLeft className="size-4" />
                    Back to dashboard
                </Link>

                <header className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div className="space-y-3">
                        <div className="bg-primary/5 text-primary inline-flex items-center gap-2 rounded-full px-3 py-1 text-xs font-medium">
                            <CarFront className="size-3.5" />
                            Fleet management
                        </div>
                        <div className="space-y-1">
                            <h1 className="text-3xl font-semibold tracking-tight">Add a vehicle</h1>
                            <p className="text-muted-foreground max-w-xl text-sm">
                                Register a vehicle and connect it to a model in your rental fleet.
                            </p>
                        </div>
                    </div>
                    <div className="text-muted-foreground hidden items-center gap-2 text-sm sm:flex">
                        <span className="bg-primary text-primary-foreground flex size-7 items-center justify-center rounded-full text-xs font-semibold">
                            1
                        </span>
                        Vehicle details
                    </div>
                </header>

                {success && (
                    <Alert className="border-emerald-500/30 bg-emerald-500/5">
                        <CheckCircle2 className="size-4 text-emerald-600" />
                        <AlertTitle>{successTitle}</AlertTitle>
                        <AlertDescription>{success}</AlertDescription>
                    </Alert>
                )}
                {formError && (
                    <Alert variant="destructive">
                        <AlertTitle>Something went wrong</AlertTitle>
                        <AlertDescription>{formError}</AlertDescription>
                    </Alert>
                )}

                <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_320px]">
                    <Card className="border-border/80 shadow-sm">
                        <CardHeader className="border-border/70 border-b">
                            <CardTitle className="text-lg">Vehicle details</CardTitle>
                            <CardDescription>Enter the VIN and choose the matching vehicle model.</CardDescription>
                        </CardHeader>
                        <CardContent className="pt-6">
                            {carModels.length === 0 ? (
                                <Alert>
                                    <CircleHelp className="size-4" />
                                    <AlertTitle>No car models available</AlertTitle>
                                    <AlertDescription>
                                        <div className="space-y-3">
                                            <p>
                                                Add a car model before registering a fleet vehicle. Once it’s added, select it from the Car model list
                                                below.
                                            </p>
                                            <Button type="button" size="sm" onClick={() => setAddingCarModel(true)}>
                                                Add car model
                                            </Button>
                                        </div>
                                    </AlertDescription>
                                </Alert>
                            ) : (
                                <form onSubmit={submit} className="space-y-6">
                                    <div className="grid gap-2">
                                        <Label htmlFor="vehicle_vin">Vehicle identification number (VIN)</Label>
                                        <Input
                                            ref={vinInputRef}
                                            id="vehicle_vin"
                                            name="vehicle_vin"
                                            value={vehicleVin}
                                            onChange={(event) => setVehicleVin(event.target.value)}
                                            placeholder="e.g. 1HGCM82633A004352"
                                            maxLength={50}
                                            autoComplete="off"
                                            aria-invalid={Boolean(errors.vehicle_vin)}
                                            aria-describedby={errors.vehicle_vin ? 'vehicle_vin-error' : undefined}
                                            required
                                        />
                                        <p className="text-muted-foreground text-xs">VINs are stored in uppercase and must be unique.</p>
                                        <InputError id="vehicle_vin-error" message={errors.vehicle_vin} />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="car_model_id">Car model</Label>
                                        <Select
                                            value={carModelId}
                                            onValueChange={(value) => {
                                                setCarModelId(value);
                                                setErrors((current) => ({ ...current, car_model_id: '' }));
                                            }}
                                        >
                                            <SelectTrigger
                                                id="car_model_id"
                                                aria-invalid={Boolean(errors.car_model_id)}
                                                aria-describedby={errors.car_model_id ? 'car_model_id-error' : undefined}
                                            >
                                                <SelectValue placeholder="Choose a model" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {carModels.map((model) => (
                                                    <SelectItem key={model.id} value={String(model.id)}>
                                                        {model.model_name} · {formatCurrency(model.daily_rate)}/day
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                        <InputError id="car_model_id-error" message={errors.car_model_id} />
                                    </div>

                                    <div className="border-border/70 flex flex-col-reverse gap-3 border-t pt-5 sm:flex-row sm:justify-end">
                                        <Button asChild variant="outline">
                                            <Link href="/dashboard">Cancel</Link>
                                        </Button>
                                        <Button type="submit" disabled={processing || !carModelId}>
                                            {processing ? <LoaderCircle className="animate-spin" /> : <CarFront />}
                                            {processing ? 'Adding vehicle…' : 'Add vehicle'}
                                        </Button>
                                    </div>
                                </form>
                            )}
                        </CardContent>
                    </Card>

                    <aside className="space-y-4">
                        <Card className="border-border/80 overflow-hidden shadow-sm">
                            <div className="from-primary/10 via-primary/5 bg-gradient-to-br to-transparent p-6">
                                <div className="bg-background mb-5 flex size-11 items-center justify-center rounded-xl shadow-sm">
                                    <TruckIcon aria-hidden="true" className="text-primary size-5" />
                                </div>
                                <h2 className="font-semibold">Fleet snapshot</h2>
                                <p className="text-muted-foreground mt-1 text-sm">Preview the vehicle details as you enter them.</p>
                                <div className="bg-background/80 mt-6 rounded-lg border p-4">
                                    <p className="text-muted-foreground text-xs font-medium tracking-wide uppercase">Selected model</p>
                                    <p className="mt-2 font-medium">{selectedModel?.model_name ?? 'No model selected'}</p>
                                    {selectedModel && (
                                        <p className="text-muted-foreground mt-1 text-sm">{formatCurrency(selectedModel.daily_rate)} per day</p>
                                    )}
                                    <p className="text-muted-foreground mt-4 text-xs font-medium tracking-wide uppercase">Vehicle VIN</p>
                                    {vehicleVin.trim() ? (
                                        <p className="mt-2 font-mono text-sm">{vehicleVin.trim().toUpperCase()}</p>
                                    ) : (
                                        <Button
                                            type="button"
                                            variant="link"
                                            className="mt-2 h-auto p-0 font-mono text-sm"
                                            onClick={() => vinInputRef.current?.focus()}
                                        >
                                            Enter a VIN to preview
                                        </Button>
                                    )}
                                </div>
                            </div>
                        </Card>
                        <div className="rounded-lg border border-dashed p-4">
                            <div className="flex gap-3">
                                <CircleHelp className="text-muted-foreground mt-0.5 size-4 shrink-0" />
                                <p className="text-muted-foreground text-sm leading-relaxed">
                                    Double-check the VIN against the vehicle registration. A VIN can only be registered once.
                                </p>
                            </div>
                        </div>
                    </aside>
                </div>
                <Card className="border-border/80 shadow-sm">
                    <CardHeader>
                        <CardTitle className="text-lg">Existing fleet vehicles</CardTitle>
                        <CardDescription>Edit a vehicle’s VIN or assigned model.</CardDescription>
                    </CardHeader>
                    <CardContent>
                        {fleetCars.length > 0 ? (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Vehicle</TableHead>
                                        <TableHead>VIN</TableHead>
                                        <TableHead className="text-right">Actions</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {fleetCars.map((fleetCar) => (
                                        <TableRow key={fleetCar.id}>
                                            <TableCell className="font-medium">{fleetCar.carModel}</TableCell>
                                            <TableCell className="font-mono text-sm">{fleetCar.vehicleVin}</TableCell>
                                            <TableCell className="text-right">
                                                <Button variant="ghost" size="sm" onClick={() => openFleetCarEditor(fleetCar)}>
                                                    Edit
                                                    <span className="sr-only"> vehicle {fleetCar.vehicleVin}</span>
                                                </Button>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        ) : (
                            <p className="text-muted-foreground py-8 text-center text-sm">No vehicles have been added yet.</p>
                        )}
                    </CardContent>
                </Card>
                <Card className="border-border/80 shadow-sm">
                    <CardHeader>
                        <CardTitle className="text-lg">Car models</CardTitle>
                        <CardDescription>Edit a model’s name or daily rental rate.</CardDescription>
                    </CardHeader>
                    <CardContent>
                        {carModels.length > 0 ? (
                            <div className="space-y-4">
                                <div className="flex justify-end">
                                    <Button type="button" onClick={() => setAddingCarModel(true)}>
                                        Add car model
                                    </Button>
                                </div>
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Model</TableHead>
                                            <TableHead>Daily rate</TableHead>
                                            <TableHead className="text-right">Actions</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {carModels.map((carModel) => (
                                            <TableRow key={carModel.id}>
                                                <TableCell className="font-medium">{carModel.model_name}</TableCell>
                                                <TableCell>{formatCurrency(carModel.daily_rate)}</TableCell>
                                                <TableCell className="text-right">
                                                    <Button variant="ghost" size="sm" onClick={() => openCarModelEditor(carModel)}>
                                                        Edit
                                                        <span className="sr-only"> model {carModel.model_name}</span>
                                                    </Button>
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            </div>
                        ) : (
                            <div className="flex flex-col items-center gap-3 py-8 text-center">
                                <p className="text-muted-foreground text-sm">
                                    Add a car model before registering a fleet vehicle. Once a model is available, you can select it here.
                                </p>
                                <Button type="button" onClick={() => setAddingCarModel(true)}>
                                    Add car model
                                </Button>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </main>
            <Dialog open={editingFleetCar !== null} onOpenChange={(open) => !open && setEditingFleetCar(null)}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Edit fleet vehicle</DialogTitle>
                        <DialogDescription>Update the vehicle identification number or assigned car model.</DialogDescription>
                    </DialogHeader>
                    {editingFleetCar && (
                        <form onSubmit={saveFleetCar} noValidate className="space-y-4">
                            {editFormError && (
                                <p role="alert" className="text-destructive text-sm">
                                    {editFormError}
                                </p>
                            )}
                            <div className="space-y-2">
                                <Label htmlFor="edit-vehicle-vin">Vehicle identification number (VIN)</Label>
                                <Input
                                    id="edit-vehicle-vin"
                                    value={editForm.vehicleVin}
                                    onChange={(event) => setEditForm((current) => ({ ...current, vehicleVin: event.target.value }))}
                                    maxLength={50}
                                    required
                                    aria-invalid={Boolean(editErrors.vehicle_vin)}
                                />
                                <InputError message={editErrors.vehicle_vin} />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="edit-car-model">Car model</Label>
                                <Select
                                    value={editForm.carModelId}
                                    onValueChange={(carModelId) => setEditForm((current) => ({ ...current, carModelId }))}
                                >
                                    <SelectTrigger id="edit-car-model" aria-invalid={Boolean(editErrors.car_model_id)}>
                                        <SelectValue placeholder="Choose a model" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {carModels.map((model) => (
                                            <SelectItem key={model.id} value={String(model.id)}>
                                                {model.model_name} · {formatCurrency(model.daily_rate)}/day
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError message={editErrors.car_model_id} />
                            </div>
                            <DialogFooter>
                                <Button type="button" variant="outline" onClick={() => setEditingFleetCar(null)} disabled={editProcessing}>
                                    Cancel
                                </Button>
                                <Button type="submit" disabled={editProcessing || carModels.length === 0}>
                                    {editProcessing ? 'Saving…' : 'Save changes'}
                                </Button>
                            </DialogFooter>
                        </form>
                    )}
                </DialogContent>
            </Dialog>
            <Dialog open={editingCarModel !== null} onOpenChange={(open) => !open && setEditingCarModel(null)}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Edit car model</DialogTitle>
                        <DialogDescription>Update the model name or daily rental rate.</DialogDescription>
                    </DialogHeader>
                    {editingCarModel && (
                        <form onSubmit={saveCarModel} noValidate className="space-y-4">
                            {carModelFormError && (
                                <p role="alert" className="text-destructive text-sm">
                                    {carModelFormError}
                                </p>
                            )}
                            <div className="space-y-2">
                                <Label htmlFor="edit-model-name">Model name</Label>
                                <Input
                                    id="edit-model-name"
                                    value={carModelForm.modelName}
                                    onChange={(event) => setCarModelForm((current) => ({ ...current, modelName: event.target.value }))}
                                    maxLength={100}
                                    required
                                    aria-invalid={Boolean(carModelErrors.model_name)}
                                />
                                <InputError message={carModelErrors.model_name} />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="edit-daily-rate">Daily rate</Label>
                                <Input
                                    id="edit-daily-rate"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    value={carModelForm.dailyRate}
                                    onChange={(event) => setCarModelForm((current) => ({ ...current, dailyRate: event.target.value }))}
                                    required
                                    aria-invalid={Boolean(carModelErrors.daily_rate)}
                                />
                                <InputError message={carModelErrors.daily_rate} />
                            </div>
                            <DialogFooter>
                                <Button type="button" variant="outline" onClick={() => setEditingCarModel(null)} disabled={carModelProcessing}>
                                    Cancel
                                </Button>
                                <Button type="submit" disabled={carModelProcessing}>
                                    {carModelProcessing ? 'Saving…' : 'Save changes'}
                                </Button>
                            </DialogFooter>
                        </form>
                    )}
                </DialogContent>
            </Dialog>
            <Dialog open={addingCarModel} onOpenChange={setAddingCarModel}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Add car model</DialogTitle>
                        <DialogDescription>Create a model and set its daily rental rate before adding a fleet vehicle.</DialogDescription>
                    </DialogHeader>
                    <form onSubmit={createCarModel} noValidate className="space-y-4">
                        {newCarModelFormError && (
                            <p role="alert" className="text-destructive text-sm">
                                {newCarModelFormError}
                            </p>
                        )}
                        <div className="space-y-2">
                            <Label htmlFor="new-model-name">Model name</Label>
                            <Input
                                id="new-model-name"
                                value={newCarModelForm.modelName}
                                onChange={(event) => setNewCarModelForm((current) => ({ ...current, modelName: event.target.value }))}
                                maxLength={100}
                                required
                                aria-invalid={Boolean(newCarModelErrors.model_name)}
                            />
                            <InputError message={newCarModelErrors.model_name} />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="new-daily-rate">Daily rate</Label>
                            <Input
                                id="new-daily-rate"
                                type="number"
                                min="0"
                                step="0.01"
                                value={newCarModelForm.dailyRate}
                                onChange={(event) => setNewCarModelForm((current) => ({ ...current, dailyRate: event.target.value }))}
                                required
                                aria-invalid={Boolean(newCarModelErrors.daily_rate)}
                            />
                            <InputError message={newCarModelErrors.daily_rate} />
                        </div>
                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={() => setAddingCarModel(false)} disabled={newCarModelProcessing}>
                                Cancel
                            </Button>
                            <Button type="submit" disabled={newCarModelProcessing}>
                                {newCarModelProcessing ? 'Saving…' : 'Add model'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
