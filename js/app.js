const app = new Vue({
    el: '#app',
    data: () => ({
        user: {
            firstname: '',
            name: '',
            tel: '',
            street: '',
            number: '',
            city: '',
            zip: '',
        },
        name: '',
        email: '',
        submitted: 'true',
        error: '',
        active: '',
        loading: false,
    }),
    computed: {
        date() {
            return new Intl.DateTimeFormat('de-DE', {
                weekday: 'long', year: 'numeric', month: '2-digit', day: '2-digit',
            }).format(new Date())
        },
        loggedIn() {
            return this.active.length > 0
        }
    },
    mounted() {
        const user = localStorage.getItem('user')
        if (user) {
            this.user = JSON.parse(user)
        }
        this.active = localStorage.getItem('active') || ''
    },
    methods: {
        isHoneypotFilled() {
            return this.name.length || this.email.length
        },
        async submit() {
            this.loading = true
            this.error = ''
            if (this.isHoneypotFilled()) {
                console.error('Honeypot is filled. Everybody hates bots...')
                this.loading = false
            } else if (!this.$refs.form.checkValidity()) {
                this.$refs.form.classList.add('was-validated')
                this.loading = false
            } else {
                const dataAsString = JSON.stringify(this.user)
                localStorage.setItem('user', dataAsString)

                fetch('/wp-json/corona/in', {
                    method: 'POST',
                    headers: {
                        'Content-type': 'application/json; charset=UTF-8',
                    },
                    body: dataAsString,
                })
                    .then(async response => {
                        if (!response.ok) {
                            this.error = await response.json().message
                            this.loading = false
                            return
                        }

                        const date =  new Intl.DateTimeFormat('de-DE', {
                            weekday: 'long', year: 'numeric', month: '2-digit', day: '2-digit',
                            hour: 'numeric', minute: 'numeric', second: 'numeric',
                        }).format(new Date())
                        localStorage.setItem('active', date)
                        this.active = date
                    })
                    .catch(e => {
                        this.error = e
                    })
                    .finally(() => {
                        this.loading = false
                    })
            }
        },
        async logout() {
            this.loading = true
            this.error = ''
            fetch('/wp-json/corona/out', {
                method: 'POST',
                headers: {
                    'Content-type': 'application/json; charset=UTF-8',
                },
                body: JSON.stringify(this.user),
            })
                .then(async response => {
                    if (!response.ok) {
                        this.error = await response.json().message
                        this.loading = false
                        return
                    }

                    localStorage.removeItem('active')
                    this.active = ''
                })
                .catch(e => {
                    this.error = e
                })
                .finally(() => {
                    this.loading = false
                })
        },
    },
    template: `<div>
    <h2 class="h4">{{ date }}</h2>
    <template v-if="!loggedIn">
    <div v-if="error" class="alert alert-danger mt-3 mb-4" role="alert">
        <p class="mb-0">Es ist ein Fehler aufgetreten. {{error}} Bitte versuche es erneut.</p>
    </div>
<form v-else ref="form" class="row needs-validation mb-5 g-3" @submit.prevent="submit" novalidate>
    <div class="col-md-6">
        <label for="psvfirstname" class="form-label">Vorname<span class="mandatory">*</span></label>
        <input type="text" class="form-control" id="psvfirstname" v-model="user.firstname" placeholder="Vorname"
               autocomplete="given-name" required>
            <div class="invalid-feedback">
                Bitte gib deinen Vornamen an.
            </div>
    </div>
    <div class="col-md-6">
        <label for="psvname" class="form-label">Nachname<span class="mandatory">*</span></label>
        <input type="text" class="form-control" id="psvname" v-model="user.name" placeholder="Nachname"
               autocomplete="family-name" required>
            <div class="invalid-feedback">
                Bitte gib deinen Nachnamen an.
            </div>
    </div>
    <div class="col-12">
        <label for="psvtel" class="form-label">Telefonnr.<span class="mandatory">*</span></label>
        <input type="tel" class="form-control" id="psvtel" v-model="user.tel" placeholder="Telefonnr." required>
            <div class="invalid-feedback">
                Bitte gib eine gültige Telefonnummer an.
            </div>
    </div>
    <div class="col-md-8">
        <label for="psvstreet" class="form-label">Straße<span class="mandatory">*</span></label>
        <input type="text" class="form-control" id="psvstreet" v-model="user.street" placeholder="Straße" required>
            <div class="invalid-feedback">
                Bitte gib deine Straße an.
            </div>
    </div>
    <div class="col-md-4">
        <label for="psvnumber" class="form-label">Hausnr.<span class="mandatory">*</span></label>
        <input type="text" class="form-control" id="psvnumber" v-model="user.number" placeholder="Hausnr." required>
            <div class="invalid-feedback">
                Bitte gib deine Hausnummer an.
            </div>
    </div>
    <div class="col-md-6">
        <label for="psvzip" class="form-label">PLZ<span class="mandatory">*</span></label>
        <input type="text" class="form-control" id="psvzip" v-model="user.zip" placeholder="PLZ" required>
            <div class="invalid-feedback">
                Bitte gib deine PLZ an.
            </div>
    </div>
    <div class="col-md-6">
        <label for="psvcity" class="form-label">Ort<span class="mandatory">*</span></label>
        <input type="text" class="form-control" id="psvcity" v-model="user.city" placeholder="Ort" required>
            <div class="invalid-feedback">
                Bitte gib deinen Wohnort an.
            </div>
    </div>

    <!-- H o n e y p o t -->
    <label class="ohnohoney" for="name"></label>
    <input class="ohnohoney" autocomplete="off" type="text" id="name" v-model="name" placeholder="Your name here">
        <label class="ohnohoney" for="email"></label>
        <input class="ohnohoney" autocomplete="off" type="email" id="email" v-model="email"
               placeholder="Your e-mail here">

            <div class="col-12">
                <button type="submit" :disabled="loading" id="corona-form-submit" class="btn btn-primary float-right">
                    <span v-if="loading" class="spinner-border spinner-border-sm mr-1" role="status" aria-hidden="true"></span>
                    Anmelden
                </button>
            </div>

            <input type="hidden" v-model="submitted" id="submitted"/>
</form>
</template>
<template v-else>
    <div>
        <p>Angemeldet seit <strong>{{ active.slice(0, -3) }}</strong> Uhr.</p>
        <button class="btn btn-primary" :disabled="loading" @click.prevent="logout">
            <span v-if="loading" class="spinner-border spinner-border-sm mr-1" role="status" aria-hidden="true"></span>
            Abmelden
        </button>
    </div>
</template>
</div>`,
})
