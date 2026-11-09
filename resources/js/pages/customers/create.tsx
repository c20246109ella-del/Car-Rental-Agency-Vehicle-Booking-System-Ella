import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, CheckCircle2, LoaderCircle, UserRoundPlus } from 'lucide-react';
import { type FormEvent, useState } from 'react';

import InputError from '@/components/input-error';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { ApiFormError, submitJsonForm } from '@/lib/submit-json-form';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Rentals', href: '/rentals/create' },
    { title: 'Add customer', href: '/customers/create' },
];

export default function CreateCustomer() {
    const [driverLicense, setDriverLicense] = useState('');
    const [customerName, setCustomerName] = useState('');
    const [customerPhone, setCustomerPhone] = useState('');
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [processing, setProcessing] = useState(false);
    const [success, setSuccess] = useState('');
    const [formError, setFormError] = useState('');

    async function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        setErrors({});
        setFormError('');
        setSuccess('');
        setProcessing(true);

        try {
            const result = await submitJsonForm(route('customers.store'), {
                driver_license: driverLicense.trim(),
                customer_name: customerName.trim(),
                customer_phone: customerPhone.trim(),
            });
            setSuccess(result.message ?? 'Customer created successfully.');
            setDriverLicense('');
            setCustomerName('');
            setCustomerPhone('');
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

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Add customer" />
            <main className="mx-auto w-full max-w-3xl space-y-8 p-4 md:p-8">
                <Link
                    href="/rentals/create"
                    className="text-muted-foreground hover:text-foreground inline-flex items-center gap-2 text-sm transition-colors"
                >
                    <ArrowLeft className="size-4" />
                    Back to rental form
                </Link>

                <header className="space-y-3">
                    <div className="bg-primary/5 text-primary inline-flex items-center gap-2 rounded-full px-3 py-1 text-xs font-medium">
                        <UserRoundPlus className="size-3.5" />
                        Customer management
                    </div>
                    <div className="space-y-1">
                        <h1 className="text-3xl font-semibold tracking-tight">Add a customer</h1>
                        <p className="text-muted-foreground max-w-xl text-sm">Save customer details to make them available for rental bookings.</p>
                    </div>
                </header>

                {success && (
                    <Alert className="border-emerald-500/30 bg-emerald-500/5">
                        <CheckCircle2 className="size-4 text-emerald-600" />
                        <AlertTitle>Customer added</AlertTitle>
                        <AlertDescription>{success}</AlertDescription>
                    </Alert>
                )}
                {formError && (
                    <Alert variant="destructive">
                        <AlertTitle>Something went wrong</AlertTitle>
                        <AlertDescription>{formError}</AlertDescription>
                    </Alert>
                )}

                <Card className="border-border/80 shadow-sm">
                    <CardHeader className="border-border/70 border-b">
                        <CardTitle className="text-lg">Customer details</CardTitle>
                        <CardDescription>Driver licenses are stored in uppercase and must be unique.</CardDescription>
                    </CardHeader>
                    <CardContent className="pt-6">
                        <form onSubmit={submit} className="space-y-6">
                            <div className="grid gap-2">
                                <Label htmlFor="driver_license">Driver license</Label>
                                <Input
                                    id="driver_license"
                                    value={driverLicense}
                                    onChange={(event) => setDriverLicense(event.target.value)}
                                    maxLength={50}
                                    autoComplete="off"
                                    aria-invalid={Boolean(errors.driver_license)}
                                    aria-describedby={errors.driver_license ? 'driver_license-error' : undefined}
                                    required
                                />
                                <InputError id="driver_license-error" message={errors.driver_license} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="customer_name">Full name</Label>
                                <Input
                                    id="customer_name"
                                    value={customerName}
                                    onChange={(event) => setCustomerName(event.target.value)}
                                    maxLength={100}
                                    autoComplete="name"
                                    aria-invalid={Boolean(errors.customer_name)}
                                    aria-describedby={errors.customer_name ? 'customer_name-error' : undefined}
                                    required
                                />
                                <InputError id="customer_name-error" message={errors.customer_name} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="customer_phone">Phone number</Label>
                                <Input
                                    id="customer_phone"
                                    type="tel"
                                    value={customerPhone}
                                    onChange={(event) => setCustomerPhone(event.target.value)}
                                    maxLength={20}
                                    autoComplete="tel"
                                    aria-invalid={Boolean(errors.customer_phone)}
                                    aria-describedby={errors.customer_phone ? 'customer_phone-error' : undefined}
                                    required
                                />
                                <InputError id="customer_phone-error" message={errors.customer_phone} />
                            </div>

                            <div className="border-border/70 flex flex-col-reverse gap-3 border-t pt-5 sm:flex-row sm:justify-end">
                                <Button asChild variant="outline">
                                    <Link href="/rentals/create">Cancel</Link>
                                </Button>
                                <Button type="submit" disabled={processing}>
                                    {processing ? <LoaderCircle className="animate-spin" /> : <UserRoundPlus />}
                                    {processing ? 'Adding customer…' : 'Add customer'}
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </main>
        </AppLayout>
    );
}
