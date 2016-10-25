'''
Created on Oct 18, 2016

@author: KARNUTJ
'''

import pandas as pd
import time
import json
from pprint import pprint

state_reference = {"AL":"Alabama",
                   "AK":"Alaska",
                   "AZ": "Arizona",
                   "AR":"Arkansas",
                   "CA":"California",
                   "CO":"Colorado",
                   "CT":"Connecticut",
                   "DC": "District of Columbia",
                   "DE":"Delaware",
                   "FL":"Florida",
                   "GA":"Georgia",
                   "HI":"Hawaii",
                   "ID":"Idaho",
                   "IL":"Illinois",
                   "IN":"Indiana",
                   "IA":"Iowa",
                   "KS":"Kansas",
                   "KY":"Kentucky",
                   "LA":"Louisiana",
                   "ME":"Maine",
                   "MD":"Maryland",
                   "MA":"Massachusetts",
                   "MI":"Michigan",
                   "MN":"Minnesota",
                   "MS":"Mississippi",
                   "MO":"Missouri",
                   "MT":"Montana",
                   "NE":"Nebraska",
                   "NV":"Nevada",
                   "NH":"New Hampshire",
                   "NJ":"New Jersey",
                   "NM":"New Mexico",
                   "NY":"New York",
                   "NC":"North Carolina",
                   "ND": "North Dakota",
                   "OH":"Ohio",
                   "OK":"Oklahoma",
                   "OR":"Oregon",
                   "PA":"Pennsylvania",
                   "PR":"Puerto Rico",
                   "RI":"Rhode Island",
                   "SC":"South Carolina",
                   "SD":"South Dakota",
                   "TN":"Tennessee",
                   "TX":"Texas",
                   "UT":"Utah",
                   "VT":"Vermont",
                   "VA":"Virginia",
                   "WA":"Washington",
                   "WV":"West Virginia",
                   "WI":"Wisconsin",
                   "WY":"Wyoming"}

def abstract(fpath):
    init_time = time.time()
    #setup
    pd.set_option('display.max_columns', None)
    #read data
    data = pd.read_table(fpath, low_memory=False)
    print("loaded data in: " + str(time.time()-init_time))
    init_time = time.time()
    #logic
    print(list(data.columns.values))
#     print(data.ix[:5])
    
    #make dict of states and total medicare:
    # 0 - submitted
    # 1 - allowed amount
    # 2 - payment amount
    sub = "total_med_submitted_chrg_amt"
    allowed = "total_med_medicare_allowed_amt"
    payment = "total_med_medicare_payment_amt"
    number = "number_cms_entries"
    state_code = "state_two_letter_code"
    state_s = "nppes_provider_state"
    payment_dict = {}   
    pruned = data.dropna(subset=[sub, allowed, payment, state_s])
    for index,row in pruned.iterrows():
        try:
            state = state_reference[row[state_s]]
        except KeyError:
            continue
        #0 - submitted
        #1 - allowed
        #2 - payment
        #3 - number instances
        payments = {sub: row[sub], allowed:row[allowed], 
                    payment:row[payment], number:1, state_code:row[state_s]}
        if state not in payment_dict:
            payment_dict[state] = payments
        else:
            old_payments = payment_dict[state]
            old_sub = old_payments[sub]
            old_allowed = old_payments[allowed]
            old_payment = old_payments[payment]
            old_number = old_payments[number]
            payments = {sub: row[sub] + old_sub, allowed:row[allowed]+old_allowed, 
                    payment:row[payment]+old_payment, number:1+old_number, state_code:row[state_s]}
            payment_dict[state] = payments
    print("finished in: " + str(time.time() - init_time))
    return payment_dict

if __name__ == "__main__":
    fpath = "cms_data.txt"
    ddict = abstract(fpath)
    pprint(ddict)
    with open('json_data.json', 'w') as outfile:
        json.dump(ddict, outfile, sort_keys=True)
    
    
    