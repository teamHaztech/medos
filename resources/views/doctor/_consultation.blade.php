{{-- This is the consultation panel included inside doctor/dashboard.blade.php --}}
{{-- Replaces the old consultation template x-if="showConsultation" --}}

<div class="space-y-3">
    {{-- Header --}}
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-4 flex items-center justify-between">
        <div>
            <h3 class="font-bold text-slate-900" x-text="selectedPatient.name + ' · ' + selectedPatient.token"></h3>
            <p class="text-xs text-slate-500" x-text="selectedPatient.complaint"></p>
        </div>
        <div class="flex items-center gap-2">
            <template x-if="selectedPatient.allergies?.length">
                <span class="px-2 py-0.5 bg-red-100 text-red-800 rounded text-xs font-medium" x-text="'⚠ ' + selectedPatient.allergies.join(', ')"></span>
            </template>
            <span class="px-3 py-1 rounded-full text-sm font-medium bg-purple-100 text-purple-800">In Progress</span>
        </div>
    </div>

    {{-- TABS --}}
    <div class="bg-white rounded-xl shadow-sm border border-slate-200">
        <div class="flex border-b border-slate-200 overflow-x-auto">
            <template x-for="t in [{id:'vitals',label:'Vitals'},{id:'diagnosis',label:'Diagnosis'},{id:'tests',label:'Tests'},{id:'rx',label:'Rx'},{id:'refer',label:'Refer'},{id:'notes',label:'Notes'}]" :key="t.id">
                <button @click="cTab=t.id" :class="cTab===t.id?'border-blue-600 text-blue-700 bg-blue-50/50':'border-transparent text-slate-500'" class="flex-1 py-2.5 text-xs font-semibold border-b-2 transition-all whitespace-nowrap px-2" x-text="t.label"></button>
            </template>
        </div>

        <div class="p-4">

            {{-- VITALS --}}
            <div x-show="cTab==='vitals'">
                <div class="grid grid-cols-3 sm:grid-cols-6 gap-2 mb-3">
                    <div><label class="text-[10px] font-semibold text-slate-400 uppercase">BP</label><input type="text" x-model="vitals.bp" class="input-field mt-0.5 text-center font-bold" placeholder="120/80"></div>
                    <div><label class="text-[10px] font-semibold text-slate-400 uppercase">Temp °F</label><input type="text" x-model="vitals.temp" class="input-field mt-0.5 text-center font-bold" placeholder="98.6"></div>
                    <div><label class="text-[10px] font-semibold text-slate-400 uppercase">Pulse</label><input type="text" x-model="vitals.pulse" class="input-field mt-0.5 text-center font-bold" placeholder="72"></div>
                    <div><label class="text-[10px] font-semibold text-slate-400 uppercase">SpO2 %</label><input type="text" x-model="vitals.spo2" class="input-field mt-0.5 text-center font-bold" placeholder="98"></div>
                    <div><label class="text-[10px] font-semibold text-slate-400 uppercase">Weight kg</label><input type="text" x-model="vitals.weight" class="input-field mt-0.5 text-center font-bold" placeholder="70"></div>
                    <div><label class="text-[10px] font-semibold text-slate-400 uppercase">RR</label><input type="text" x-model="vitals.rr" class="input-field mt-0.5 text-center font-bold" placeholder="16"></div>
                </div>
                <textarea x-model="vitals.examNotes" rows="2" class="input-field text-sm" placeholder="Physical exam findings..."></textarea>
            </div>

            {{-- DIAGNOSIS --}}
            <div x-show="cTab==='diagnosis'">
                <p class="text-[10px] font-semibold text-slate-400 uppercase mb-1.5">Quick Select</p>
                <div class="flex flex-wrap gap-1 mb-3">
                    <template x-for="d in commonDiagnoses" :key="d.code">
                        <button @click="toggleDiagnosis(d)" :class="selectedDiagnoses.find(s=>s.code===d.code)?'bg-blue-500 text-white':'bg-slate-100 text-slate-700 hover:bg-slate-200'" class="px-2 py-1 rounded text-xs font-medium transition-all" x-text="d.name"></button>
                    </template>
                </div>
                <div class="flex gap-2">
                    <input type="text" x-model="customDiagnosis" class="input-field flex-1 text-sm" placeholder="Custom diagnosis..." @keydown.enter.prevent="if(customDiagnosis){selectedDiagnoses.push({code:'CUSTOM',name:customDiagnosis});customDiagnosis='';}">
                    <button @click="if(customDiagnosis){selectedDiagnoses.push({code:'CUSTOM',name:customDiagnosis});customDiagnosis='';}" class="btn-primary text-xs px-3">Add</button>
                </div>
                <template x-if="selectedDiagnoses.length">
                    <div class="mt-2 flex flex-wrap gap-1">
                        <template x-for="(d,i) in selectedDiagnoses" :key="i">
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 bg-blue-100 text-blue-800 rounded text-xs font-medium">
                                <span x-text="d.name"></span>
                                <button @click="selectedDiagnoses.splice(i,1)" class="text-blue-400 hover:text-blue-700">&times;</button>
                            </span>
                        </template>
                    </div>
                </template>
            </div>

            {{-- TESTS (from DB) --}}
            <div x-show="cTab==='tests'">
                <div class="flex gap-2 mb-3">
                    <button @click="testFilter='all'" :class="testFilter==='all'?'bg-slate-800 text-white':'bg-slate-100'" class="px-3 py-1 rounded text-xs font-semibold">All</button>
                    <button @click="testFilter='lab'" :class="testFilter==='lab'?'bg-cyan-500 text-white':'bg-cyan-50 text-cyan-700'" class="px-3 py-1 rounded text-xs font-semibold">Lab</button>
                    <button @click="testFilter='imaging'" :class="testFilter==='imaging'?'bg-purple-500 text-white':'bg-purple-50 text-purple-700'" class="px-3 py-1 rounded text-xs font-semibold">Imaging</button>
                    <button @click="testFilter='procedure'" :class="testFilter==='procedure'?'bg-amber-500 text-white':'bg-amber-50 text-amber-700'" class="px-3 py-1 rounded text-xs font-semibold">Procedures</button>
                </div>
                <div class="flex flex-wrap gap-1 max-h-[200px] overflow-y-auto">
                    <template x-for="t in filteredTests" :key="t.id">
                        <button @click="toggleTest(t)" :class="orders.find(o=>o.id===t.id)?getTestActiveClass(t.type):getTestClass(t.type)" class="px-2 py-1 rounded text-xs font-medium transition-all">
                            <span x-text="t.name"></span>
                            <span class="text-[10px] opacity-60" x-text="t.price > 0 ? ' ₹'+t.price : ''"></span>
                        </button>
                    </template>
                </div>
                <template x-if="orders.length">
                    <div class="mt-2 p-2 bg-slate-50 rounded-lg">
                        <p class="text-[10px] font-semibold text-slate-400 uppercase mb-1">Ordered (<span x-text="orders.length"></span>)</p>
                        <div class="flex flex-wrap gap-1">
                            <template x-for="(o,i) in orders" :key="o.id">
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs font-medium" :class="o.type==='lab'?'bg-cyan-100 text-cyan-800':o.type==='imaging'?'bg-purple-100 text-purple-800':'bg-amber-100 text-amber-800'">
                                    <span x-text="o.name"></span>
                                    <button @click="orders.splice(i,1)" class="opacity-50 hover:opacity-100">&times;</button>
                                </span>
                            </template>
                        </div>
                    </div>
                </template>
            </div>

            {{-- PRESCRIPTION (from DB with search) --}}
            <div x-show="cTab==='rx'">
                <div class="relative mb-3">
                    <input type="text" x-model="medSearch" @input.debounce.300ms="searchMedicines()" class="input-field text-sm pr-8" placeholder="Search medicines... (type 2+ chars)">
                    <div x-show="medSearch.length >= 2 && medResults.length" class="absolute z-20 left-0 right-0 top-full mt-1 bg-white border border-slate-200 rounded-lg shadow-xl max-h-[200px] overflow-y-auto">
                        <template x-for="med in medResults" :key="med.id">
                            <button @click="addMedicineFromDB(med); medSearch=''; medResults=[];" class="w-full flex items-center justify-between p-2.5 hover:bg-blue-50 text-left border-b border-slate-100 last:border-0">
                                <div>
                                    <span class="text-sm font-semibold text-slate-800" x-text="med.name"></span>
                                    <span class="text-xs text-slate-400 ml-1" x-text="med.form"></span>
                                    <span class="block text-xs text-slate-500" x-text="med.category + (med.generic_name && med.generic_name !== med.name ? ' · ' + med.generic_name : '')"></span>
                                </div>
                                <span class="text-xs text-slate-400" x-text="[med.default_dosage, med.default_frequency].filter(Boolean).join(' · ')"></span>
                            </button>
                        </template>
                    </div>
                    <div x-show="medSearch.length >= 2 && medResults.length === 0 && !medSearching" class="absolute z-20 left-0 right-0 top-full mt-1 bg-white border border-slate-200 rounded-lg shadow-xl p-3 text-center">
                        <p class="text-sm text-slate-500">No medicines found</p>
                        <button @click="addCustomMedicine()" class="text-blue-600 text-sm font-medium mt-1">+ Add "<span x-text="medSearch"></span>" as custom</button>
                    </div>
                </div>

                {{-- Current prescription --}}
                <template x-if="prescriptions.length">
                    <div class="space-y-2">
                        <template x-for="(rx,i) in prescriptions" :key="i">
                            <div class="p-3 bg-green-50 border border-green-200 rounded-lg">
                                <div class="flex items-start justify-between mb-2">
                                    <div>
                                        <span class="text-sm font-bold text-slate-900" x-text="rx.name"></span>
                                        <span class="text-sm text-slate-600" x-text="rx.dosage ? ' — ' + rx.dosage : ''"></span>
                                        <span class="text-xs text-slate-400 ml-1 capitalize" x-text="rx.form || ''"></span>
                                    </div>
                                    <button @click="prescriptions.splice(i,1)" class="text-red-400 hover:text-red-600 text-lg leading-none">&times;</button>
                                </div>

                                {{-- How many times a day --}}
                                <div class="mb-2">
                                    <p class="text-[10px] font-semibold text-slate-400 uppercase mb-1">How many times a day?</p>
                                    <div class="flex flex-wrap gap-1">
                                        <template x-for="f in [{v:'OD',l:'1x/day'},{v:'BD',l:'2x/day'},{v:'TDS',l:'3x/day'},{v:'QID',l:'4x/day'},{v:'SOS',l:'As needed'},{v:'HS',l:'Bedtime'},{v:'STAT',l:'Now (once)'}]" :key="f.v">
                                            <button @click="rx.frequency = f.v" :class="rx.frequency===f.v ? 'bg-green-600 text-white' : 'bg-white text-slate-700 border border-slate-200'" class="px-2 py-1 rounded text-xs font-medium transition-all" x-text="f.l"></button>
                                        </template>
                                    </div>
                                </div>

                                {{-- Before/After food --}}
                                <div class="mb-2">
                                    <p class="text-[10px] font-semibold text-slate-400 uppercase mb-1">When to take?</p>
                                    <div class="flex flex-wrap gap-1">
                                        <template x-for="t in [{v:'Before food',l:'Before Food',e:'🍽️ Before'},{v:'After food',l:'After Food',e:'🍽️ After'},{v:'With food',l:'With Food',e:'🍽️ With'},{v:'Empty stomach',l:'Empty Stomach',e:'Empty'},{v:'At bedtime',l:'At Bedtime',e:'🌙 Bedtime'},{v:'Any time',l:'Any Time',e:'Any time'}]" :key="t.v">
                                            <button @click="rx.timing = t.v" :class="rx.timing===t.v ? 'bg-blue-600 text-white' : 'bg-white text-slate-700 border border-slate-200'" class="px-2 py-1 rounded text-xs font-medium transition-all" x-text="t.l"></button>
                                        </template>
                                    </div>
                                </div>

                                {{-- Duration --}}
                                <div>
                                    <p class="text-[10px] font-semibold text-slate-400 uppercase mb-1">For how long?</p>
                                    <div class="flex flex-wrap gap-1">
                                        <template x-for="d in ['3 days','5 days','7 days','10 days','14 days','30 days','Ongoing']" :key="d">
                                            <button @click="rx.duration = d" :class="rx.duration===d ? 'bg-purple-600 text-white' : 'bg-white text-slate-700 border border-slate-200'" class="px-2 py-1 rounded text-xs font-medium transition-all" x-text="d"></button>
                                        </template>
                                    </div>
                                </div>

                                {{-- Summary line --}}
                                <div class="mt-2 pt-2 border-t border-green-200" x-show="rx.frequency || rx.timing || rx.duration">
                                    <p class="text-xs text-green-800 font-medium" x-text="[rx.frequency ? freqLabel(rx.frequency) : '', rx.timing || '', rx.duration ? 'for ' + rx.duration : ''].filter(Boolean).join(' · ')"></p>
                                </div>
                            </div>
                        </template>
                    </div>
                </template>
                <p x-show="!prescriptions.length" class="text-sm text-slate-400 text-center py-4">Search and add medicines above</p>
            </div>

            {{-- REFER + FOLLOW-UP --}}
            <div x-show="cTab==='refer'">
                {{-- Urgency --}}
                <p class="text-[10px] font-semibold text-slate-400 uppercase mb-1.5">Referral Urgency</p>
                <div class="flex gap-1.5 mb-3">
                    <button @click="referralUrgency='emergency'" :class="referralUrgency==='emergency'?'bg-red-500 text-white ring-2 ring-red-300':'bg-red-50 text-red-700 hover:bg-red-100'" class="flex-1 py-2 rounded-lg text-xs font-semibold transition-all text-center">
                        🔴 Emergency
                    </button>
                    <button @click="referralUrgency='priority'" :class="referralUrgency==='priority'?'bg-amber-500 text-white ring-2 ring-amber-300':'bg-amber-50 text-amber-700 hover:bg-amber-100'" class="flex-1 py-2 rounded-lg text-xs font-semibold transition-all text-center">
                        🟡 Priority
                    </button>
                    <button @click="referralUrgency='normal'" :class="referralUrgency==='normal'?'bg-green-500 text-white ring-2 ring-green-300':'bg-green-50 text-green-700 hover:bg-green-100'" class="flex-1 py-2 rounded-lg text-xs font-semibold transition-all text-center">
                        🟢 Normal
                    </button>
                </div>

                {{-- Doctor selection --}}
                <p class="text-[10px] font-semibold text-slate-400 uppercase mb-1.5">Select Doctor</p>
                <div class="flex flex-wrap gap-1.5 mb-3">
                    <template x-for="doc in otherDoctors" :key="doc.id">
                        <button @click="selectReferral(doc)" :class="referral?.id===doc.id?'bg-blue-500 text-white':'bg-slate-100 text-slate-700 hover:bg-slate-200'" class="px-3 py-1.5 rounded-lg text-xs font-medium transition-all">
                            <span x-text="doc.name"></span>
                            <span class="opacity-60 text-[10px] ml-0.5" x-text="doc.department"></span>
                        </button>
                    </template>
                </div>

                {{-- Calendar + Slot picker --}}
                <template x-if="referral && referralDays.length">
                    <div class="border border-blue-200 rounded-lg overflow-hidden mb-3">
                        {{-- Calendar day tabs --}}
                        <div class="bg-blue-50 p-2 flex gap-1 overflow-x-auto">
                            <template x-for="day in referralDays" :key="day.date">
                                <button @click="referralSelectedDay = day"
                                    :class="referralSelectedDay?.date===day.date ? 'bg-blue-500 text-white shadow-sm' : day.available > 0 ? 'bg-white text-slate-700 hover:bg-blue-100' : 'bg-slate-100 text-slate-400'"
                                    class="flex flex-col items-center px-3 py-2 rounded-lg text-center min-w-[60px] transition-all flex-shrink-0">
                                    <span class="text-[10px] font-semibold" x-text="day.day"></span>
                                    <span class="text-sm font-bold" x-text="day.dateFmt.split(' ')[1]"></span>
                                    <span class="text-[10px]" :class="day.available > 0 ? 'text-green-600' : 'text-red-400'" x-text="day.available + ' free'"></span>
                                </button>
                            </template>
                        </div>

                        {{-- Time slots — only show future available --}}
                        <template x-if="referralSelectedDay">
                            <div class="p-3">
                                <p class="text-xs font-semibold text-slate-700 mb-2" x-text="referralSelectedDay.dayFull + ', ' + referralSelectedDay.dateFmt"></p>
                                <div class="flex flex-wrap gap-1.5">
                                    <template x-for="slot in getAvailableSlots(referralSelectedDay)" :key="slot.time">
                                        <button @click="referralSlot = {date:referralSelectedDay.date, time:slot.time, display:slot.display}"
                                            :class="referralSlot?.date===referralSelectedDay.date && referralSlot?.time===slot.time ? 'bg-green-500 text-white ring-2 ring-green-300' : 'bg-white text-slate-700 border border-slate-200 hover:bg-green-50 hover:border-green-300'"
                                            class="px-2.5 py-1.5 rounded-lg text-xs font-medium transition-all" x-text="slot.display">
                                        </button>
                                    </template>
                                </div>
                                <p x-show="getAvailableSlots(referralSelectedDay).length === 0" class="text-xs text-slate-400 text-center py-2">No available slots this day</p>
                                <template x-if="referralSlot?.date === referralSelectedDay?.date">
                                    <p class="mt-2 text-sm text-green-700 font-semibold">✓ <span x-text="referralSlot.display + ', ' + referralSelectedDay.dateFmt"></span></p>
                                </template>
                            </div>
                        </template>
                    </div>
                </template>

                <template x-if="referral && !referralDays.length && !referralLoading">
                    <p class="text-sm text-slate-400 text-center py-3 mb-3">No schedule found for this doctor</p>
                </template>
                <template x-if="referralLoading">
                    <p class="text-sm text-slate-400 text-center py-3 mb-3">Loading schedule...</p>
                </template>

                <textarea x-model="referralReason" rows="2" class="input-field text-sm mb-4" placeholder="Referral reason..."></textarea>

                {{-- Follow-up --}}
                <p class="text-[10px] font-semibold text-slate-400 uppercase mb-1.5">Follow-up with Me</p>
                <div class="flex flex-wrap gap-1.5">
                    <template x-for="f in ['3 days','1 week','2 weeks','1 month','3 months']" :key="f">
                        <button @click="followUp = followUp===f ? null : f" :class="followUp===f?'bg-blue-500 text-white':'bg-slate-100 text-slate-700'" class="px-3 py-1.5 rounded-lg text-xs font-medium transition-all" x-text="f"></button>
                    </template>
                </div>
            </div>

            {{-- NOTES + ASSESSMENT --}}
            <div x-show="cTab==='notes'">
                <div class="space-y-3">
                    <div>
                        <label class="text-[10px] font-semibold text-slate-400 uppercase">Subjective</label>
                        <textarea x-model="soap.subjective" rows="2" class="input-field mt-0.5 text-sm" placeholder="Patient reports..."></textarea>
                    </div>
                    <div>
                        <label class="text-[10px] font-semibold text-slate-400 uppercase">Assessment — Quick Templates</label>
                        <div class="flex flex-wrap gap-1 mt-1 mb-1.5">
                            <template x-for="tpl in assessmentTemplates" :key="tpl.label">
                                <button @click="soap.assessment = (soap.assessment ? soap.assessment + '. ' : '') + tpl.text" class="px-2 py-1 bg-blue-50 text-blue-700 hover:bg-blue-100 rounded text-xs font-medium transition-all" x-text="tpl.label"></button>
                            </template>
                        </div>
                        <textarea x-model="soap.assessment" rows="2" class="input-field text-sm" placeholder="Assessment..."></textarea>
                    </div>
                    <div>
                        <label class="text-[10px] font-semibold text-slate-400 uppercase">Plan — Quick Templates</label>
                        <div class="flex flex-wrap gap-1 mt-1 mb-1.5">
                            <template x-for="tpl in planTemplates" :key="tpl.label">
                                <button @click="soap.plan = (soap.plan ? soap.plan + '. ' : '') + tpl.text" class="px-2 py-1 bg-green-50 text-green-700 hover:bg-green-100 rounded text-xs font-medium transition-all" x-text="tpl.label"></button>
                            </template>
                        </div>
                        <textarea x-model="soap.plan" rows="2" class="input-field text-sm" placeholder="Plan..."></textarea>
                    </div>
                    <div>
                        <label class="text-[10px] font-semibold text-slate-400 uppercase">Advice to Patient</label>
                        <div class="flex flex-wrap gap-1 mt-1">
                            <template x-for="a in commonAdvice" :key="a">
                                <button @click="toggleAdvice(a)" :class="advice.includes(a)?'bg-blue-500 text-white':'bg-slate-100 text-slate-700'" class="px-2 py-1 rounded text-xs font-medium transition-all" x-text="a"></button>
                            </template>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- SUMMARY BAR --}}
    <template x-if="orders.length || prescriptions.length || referral || followUp || selectedDiagnoses.length || advice.length">
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-3">
            <p class="text-[10px] font-semibold text-slate-400 uppercase mb-1.5">Consultation Summary</p>
            <div class="flex flex-wrap gap-1">
                <template x-for="d in selectedDiagnoses" :key="d.code"><span class="px-1.5 py-0.5 bg-blue-100 text-blue-800 rounded text-[11px] font-medium" x-text="d.name"></span></template>
                <template x-for="o in orders" :key="o.id"><span class="px-1.5 py-0.5 rounded text-[11px] font-medium" :class="o.type==='lab'?'bg-cyan-100 text-cyan-800':o.type==='imaging'?'bg-purple-100 text-purple-800':'bg-amber-100 text-amber-800'" x-text="o.name"></span></template>
                <template x-for="rx in prescriptions" :key="rx.name"><span class="px-1.5 py-0.5 bg-green-100 text-green-800 rounded text-[11px] font-medium" x-text="'Rx: '+rx.name+' '+rx.dosage+' '+rx.frequency+(rx.timing ? ' '+rx.timing : '')"></span></template>
                <template x-if="referral">
                    <span class="px-1.5 py-0.5 rounded text-[11px] font-medium"
                        :class="referralUrgency==='emergency'?'bg-red-100 text-red-800':referralUrgency==='priority'?'bg-amber-100 text-amber-800':'bg-green-100 text-green-800'"
                        x-text="(referralUrgency==='emergency'?'🔴 ':referralUrgency==='priority'?'🟡 ':'') + 'Refer→'+referral.name + (referralSlot ? ' '+referralSlot.display+' '+referralSlot.date : '')">
                    </span>
                </template>
                <template x-if="followUp"><span class="px-1.5 py-0.5 bg-indigo-100 text-indigo-800 rounded text-[11px] font-medium" x-text="'Follow-up:'+followUp"></span></template>
                <template x-for="a in advice" :key="a"><span class="px-1.5 py-0.5 bg-slate-100 text-slate-600 rounded text-[11px]" x-text="a"></span></template>
            </div>
        </div>
    </template>

    {{-- COMPLETE --}}
    <button @click="completeConsultation()" class="btn-success w-full py-3 text-base rounded-xl">
        ✓ Complete & Next Patient
    </button>
</div>
